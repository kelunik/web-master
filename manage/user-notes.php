<?php
// $Id$

// Force login before action can be taken
include_once 'login.inc';
include_once 'functions.inc';
include_once 'email-validation.inc';
//require_once 'alert_lib.inc'; // remove comment if alerts are needed

define("NOTES_MAIL", "php-notes@lists.php.net");

$reject_text =
'You are receiving this email because your note posted
to the online PHP manual has been removed by one of the editors.

Read the following paragraphs carefully, because they contain
pointers to resources better suited for requesting support or
reporting bugs, none of which are to be included in manual notes
because there are mechanisms and groups in place to deal with
those issues.

The user contributed notes are not an appropriate place to
ask questions, report bugs or suggest new features; please
use the resources listed on <http://php.net/support>
for those purposes. This was clearly stated in the page
you used to submit your note, please carefully re-read
those instructions before submitting future contributions.

Bug submissions and feature requests should be entered at
<http://bugs.php.net/>. For documentation errors use the
bug system, and classify the bug as "Documentation problem".
Support and ways to find answers to your questions can be found
at <http://php.net/support>.

Your note has been removed from the online manual.';

db_connect();

$action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
$id = (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : '');

if (!$action) {
  // search !
  head();

  // someting done before ?
  if ($id) {
    $str = 'Note #' . $id . ' has been ';
    switch ($_GET['was']) {
      case 'delete' :
      case 'reject' :
        $str .= ($_GET['was'] == 'delete') ? 'deleted' : 'rejected';
        $str .= ' and removed from the manual';
        break;
	
      case 'edit' :
        $str .= ' edited';
        break;
    }

    echo $str . '<br />';
  }

  if (isset($_REQUEST['keyword'])) {
    $sql = 'SELECT *,UNIX_TIMESTAMP(ts) AS ts FROM note WHERE note LIKE "%' . escape($_REQUEST['keyword']) . '%"';
    if (is_numeric($_REQUEST['keyword'])) {
      $sql .= ' OR id = ' . $_REQUEST['keyword'];
    }
    $sql .= ' LIMIT 20';

    if ($result = db_query($sql)) {
      if (mysql_num_rows($result) != 0) {
        while ($row = mysql_fetch_assoc($result)) {
          $id = $row['id'];
          echo "<p class=\"notepreview\">",clean_note($row['note']),
            "<br /><span class=\"author\">",date("d-M-Y h:i",$row['ts'])," ",
            hsc($row['user']),"</span><br />",
	    "Note id: $id<br />\n",
	    "<a href=\"http://www.php.net/manual/en/{$row['sect']}.php\">http://www.php.net/manual/en/{$row['sect']}.php</a><br />\n",
            "<a href=\"http://master.php.net/manage/user-notes.php?action=edit+$id\" target=\"_blank\">Edit Note</a><br />",
            "<a href=\"http://master.php.net/manage/user-notes.php?action=delete+$id\" target=\"_blank\">Delete Note</a><br />",
            "<a href=\"http://master.php.net/manage/user-notes.php?action=reject+$id\" target=\"_blank\">Reject Note</a>",
            "</p>",
	    "<hr />";
        }
      } else {
        echo "no results<br />";
      }
    }
  }

?>
<p>Search the notes table.</p>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
<table>
 <tr>   
  <th align="right">Keyword or ID:</th>
  <td><input type="text" name="keyword" value="<?php echo (isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : ''); ?>" size="10" maxlength="32" /></td>
 </tr>
 <tr> 
  <td align="center" colspan="2">
    <input type="submit" value="Search" />
  </td>
 </tr>
</table>
</form>

<p><a href="<?php echo $_SERVER['PHP_SELF'];?>?action=mass">Mass change of sections</a></p>
<?php
foot();
exit;
}
// end search


if (preg_match("/^(.+)\\s+(\\d+)\$/", $action, $m)) {
  $action = $m[1]; $id = $m[2];
}

switch($action) {
case 'mass':
  if (!allow_mass_change($user)) { die("You are not allowed to take this action!"); }
  head();
  $step = (isset($_REQUEST["step"]) ? $_REQUEST["step"] : 0);
  $where = array();
  if (!empty($_REQUEST["old_sect"])) {
    $where[] = "sect = '". escape($_REQUEST["old_sect"]) ."'";
  }
  if (!empty($_REQUEST["ids"])) {
    if (preg_match('~^([0-9]+, *)*[0-9]+$~i', $_REQUEST["ids"])) {
      $where[] = "id IN ($_REQUEST[ids])";
    } else {
      echo "<p><b>Incorrect format of notes IDs.</b></p>\n";
      $step = 0;
    }
  }
  
  if ($step == 2) {
    db_query("UPDATE note SET sect = '". escape($_REQUEST["new_sect"]) ."' WHERE " . implode(" AND ", $where));
    echo "<p>Mass change succeeded.</p>\n";
  } elseif ($step == 1) {
    if (!empty($_REQUEST["new_sect"]) && $where) {
      $result = db_query("SELECT COUNT(*) FROM note WHERE " . implode(" AND ", $where));
      if (!($count = mysql_result($result, 0, 0))) {
        echo "<p>There are no such notes.</p>\n";
      } else {
        $step = 2;
        $msg = "Are you sure to change section of <b>$count note(s)</b>";
        $msg .= (!empty($_REQUEST["ids"]) ? " with IDs <b>$_REQUEST[ids]</b>" : "");
        $msg .= (!empty($_REQUEST["old_sect"]) ? " from section <b>$_REQUEST[old_sect]</b>" : "");
        $msg .= " to section <b>$_REQUEST[new_sect]</b>?";
        echo "<p>$msg</p>\n";
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=mass" method="post">
<input type="hidden" name="step" value="2">
<input type="hidden" name="old_sect" value="<?php echo $_REQUEST["old_sect"]; ?>">
<input type="hidden" name="ids" value="<?php echo $_REQUEST["ids"]; ?>">
<input type="hidden" name="new_sect" value="<?php echo $_REQUEST["new_sect"]; ?>">
<input type="submit" value="Change">
</form>
<?php
      }
    } else {
      if (empty($_REQUEST["new_sect"])) {
        echo "<p><b>You have to fill-in new section.</b></p>\n";
      }
      if (!$where) {
        echo "<p><b>You have to fill-in curent section or notes IDs (or both).</b></p>\n";
      }
    }
  }
  if ($step < 2) {
?>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=mass" method="post">
<input type="hidden" name="step" value="1">
<p>Change section of notes which fit these criteria:</p>
<table>
 <tr>
  <th align="right">Current section:</th>
  <td><input type="text" name="old_sect" value="<?php echo $_REQUEST["old_sect"]; ?>" size="30" maxlength="80" /> (filename without extension)</td>
 </tr>
 <tr>
  <th align="right">Notes IDs:</th>
  <td><input type="text" name="ids" value="<?php echo $_REQUEST["ids"]; ?>" size="30" maxlength="80" /> (comma separated list)</td>
 </tr>
 <tr>
  <th align="right">Move to section:</th>
  <td><input type="text" name="new_sect" value="<?php echo $_REQUEST["new_sect"]; ?>" size="30" maxlength="80" /></td>
 </tr>
 <tr> 
  <td align="center" colspan="2">
    <input type="submit" value="Change" />
  </td>
 </tr>
</table>
</form>
<?php
  }
  echo "<p><a href='{$_SERVER['PHP_SELF']}'>Back to notes index</a></p>\n";
  foot();
  exit;
case 'approve':
  if ($id) {
    if ($row = note_get_by_id($id)) {
      
      if ($row['status'] != 'na') {
      	die ("Note #$id has already been approved");
      }
      
      if ($row['id'] && db_query("UPDATE note SET status=NULL WHERE id=$id")) {
        note_mail_on_action(
            $user,
            $id,
            "note {$row['id']} approved from {$row['sect']} by $user",
            "This note has been approved and will appear in the manual.\n\n----\n\n{$row['note']}"
        );
      }
      
      print "Note #$id has been approved and will appear in the manual";
      exit;
    }
  }
case 'reject':
case 'delete':
  if ($id) {
    if ($row = note_get_by_id($id)) {
      
      if ($row['id'] && db_query("DELETE FROM note WHERE id=$id")) {
        // ** alerts **
        //$mailto .= get_emails_for_sect($row["sect"]);
        $action_taken = ($action == "reject" ? "rejected" : "deleted");
        note_mail_on_action(
            $user,
            $id,
            "note {$row['id']} $action_taken from {$row['sect']} by $user",
            "Note Submitter: " . safe_email($row['user']) . "\n\n----\n\n{$row['note']}"
        );
        if ($action == 'reject') {
          note_mail_user($row['user'], "note $row[id] rejected and deleted from $row[sect] by notes editor $user",$reject_text."\n\n----- Copy of your note below -----\n\n".$row['note']);
        }
      }
      
      //if we came from an email, report _something_
      if (isset($_GET['report'])) {
        header('Location: user-notes.php?id=' . $id . '&was=' . $action);
        exit;
      } else {
        //if not, just close the window
        echo '<script language="javascript">window.close();</script>';
      }
      exit;
    }
  }
  /* falls through, with id not set. */
case 'preview':
case 'edit':
  if ($id) {
    $note = (isset($_POST['note']) ? escape($_POST['note']) : null);
    if (!isset($note) || $action == 'preview') {
      head();
    }

    $row = note_get_by_id($id);

    $email = (isset($_POST['email']) ? escape($_POST['email']) : addslashes($row['user']));
    $sect = (isset($_POST['sect']) ? escape($_POST['sect']) : addslashes($row['sect']));

    if (isset($note) && $action == "edit") {
      if (db_query("UPDATE note SET note='$note',user='$email',sect='$sect',updated=NOW() WHERE id=$id")) {

        // ** alerts **
        //$mailto .= get_emails_for_sect($row["sect"]);
        note_mail_on_action(
            $user,
            $id,
            "note {$row['id']} modified in {$row['sect']} by $user",
            stripslashes($note)."\n\n--was--\n{$row['note']}\n\nhttp://php.net/manual/en/{$row['sect']}.php"
        );
        if (addslashes($row["sect"]) != $sect) {
          note_mail_user($email, "note $id moved from $row[sect] to $sect by notes editor $user", "----- Copy of your note below -----\n\n".stripslashes($note));
        }
        header('Location: user-notes.php?id=' . $id . '&was=' . $action);
        exit;
      }
    }

    $note = isset($note) ? $note : addslashes($row['note']);

    if ($action == "preview") {
      echo "<p class=\"notepreview\">",clean_note(stripslashes($note)),
           "<br /><span class=\"author\">",date("d-M-Y h:i",$row['ts'])," ",
           stripslashes($email),"</span></p>";
    }
?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
<input type="hidden" name="id" value="<?php echo $id;?>" />
<table>
 <tr>
  <th align="right">Section:</th>
  <td><input type="text" name="sect" value="<?php echo stripslashes($sect);?>" size="30" maxlength="80" /></td>
 </tr>
 <tr>
  <th align="right">email:</th>
  <td><input type="text" name="email" value="<?php echo stripslashes($email);?>" size="30" maxlength="80" /></td>
 </tr>
 <tr>
  <td colspan="2"><textarea name="note" cols="70" rows="15"><?php echo stripslashes($note);?></textarea></td>
 </tr>
 <tr>
  <td align="center" colspan="2">
    <input type="submit" name="action" value="edit" />
    <input type="submit" name="action" value="preview" />
  </td>
 </tr>
</table>
</form>
<?php
    foot();
    exit;
  }
  /* falls through */
default:
  head();
  echo "<p>'$action' is not a recognized action, or no id was specified.</p>";
  foot();
}

// ----------------------------------------------------------------------------------

// Use class names instead of colors
ini_set('highlight.comment', 'comment');
ini_set('highlight.default', 'default');
ini_set('highlight.keyword', 'keyword');
ini_set('highlight.string',  'string');
ini_set('highlight.html',    'html');

// Copied over from phpweb (should be syncronised if changed)
function clean_note($text)
{
    // Highlight PHP source
    $text = highlight_php(trim($text), TRUE);

    // Turn urls into links
    $text = preg_replace(
        '!((mailto:|(http|ftp|nntp|news):\/\/).*?)(\s|<|\)|"|\\|\'|$)!',
        '<a href="\1" target="_blank">\1</a>\4',
        $text
    );
    
    return $text;
}

// Highlight PHP code
function highlight_php($code, $return = FALSE)
{
    // Using OB, as highlight_string() only supports
    // returning the result from 4.2.0
    ob_start();
    highlight_string($code);
    $highlighted = ob_get_contents();
    ob_end_clean();
    
    // Fix output to use CSS classes and wrap well
    $highlighted = '<div class="phpcode">' . str_replace(
        array(
            '&nbsp;',
            '<br />',
            '<font color="',
            '</font>',
            "\n ",
            '  '
        ),
        array(
            ' ',
            "<br />\n",
            '<span class="',
            '</span>',
            "\n&nbsp;",
            '&nbsp; '
        ),
        $highlighted
    ) . '</div>';
    
    if ($return) { return $highlighted; }
    else { echo $highlighted; }
}

// Send out a mail to the note submitter, with an envelope sender ignoring bounces
function note_mail_user($mailto, $subject, $message)
{
    $mailto = clean_antispam($mailto);
    if (is_emailable_address($mailto)) {
        mail(
            $mailto,
            $subject,
            $message,
            "From: ". NOTES_MAIL,
            "-fbounces-ignored@php.net"
        );
    }
}

// Return data about a note by its ID
function note_get_by_id($id)
{
    if ($result = db_query("SELECT *, UNIX_TIMESTAMP(ts) AS ts FROM note WHERE id='$id'")) {
        if (!mysql_num_rows($result)) {
            die("Note #$id doesn't exist. It has probably been deleted/rejected already.");
        }
        return mysql_fetch_assoc($result);
    }
    return FALSE;
}

// Sends out a notification to the mailing list when
// some action is performed on a user note.
function note_mail_on_action($user, $id, $subject, $body)
{
    mail(NOTES_MAIL, $subject, $body, "From: $user@php.net\r\nIn-Reply-To: <note-$id@php.net>");
}

// Allow some users to mass change IDs in the manual
function allow_mass_change($user)
{
    if (in_array(
            $user,
            array(
                "vrana", "goba", "nlopess", "didou"
            )
        )
    ) {
        return TRUE;
    } else { return FALSE; }
}

// Return safe to print version of email address
function safe_email($mail)
{
    if (in_array($mail, array("php-general@lists.php.net", "user@example.com"))) {
        return '';
    }
    elseif (preg_match("!(.+)@(.+)\.(.+)!", $mail)) {
        return str_replace(array('@', '.'), array(' at ', ' dot '), $mail);
    }
    return $mail;
}
