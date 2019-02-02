<?php declare(strict_types=1);
/**
 * Handle web submissions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submit';

if (!isset($_POST['submit'])) {
    header('Location: ./');
    return;
}
if (is_null($cid)) {
    require(LIBWWWDIR . '/header.php');
    echo "<p class=\"nodata\">No active contest</p>\n";
    require(LIBWWWDIR . '/footer.php');
    return;
}
$fdata = calcFreezeData($cdata);
if (!checkrole('jury') && !$fdata['started']) {
    require(LIBWWWDIR . '/header.php');
    echo "<p class=\"nodata\">Contest has not yet started.</p>\n";
    require(LIBWWWDIR . '/footer.php');
    return;
}


/** helper to output an error message. */
function err($string)
{
    // Annoying PHP: we need to import global variables here...
    global $title;

    require(LIBWWWDIR . '/header.php');

    echo "<h2>Submit - error</h2>\n\n";

    echo '<div id="uploadstatus">';
    echo specialchars($string);
    echo '</div>';

    require(LIBWWWDIR . '/footer.php');
    return;
}

// rebuild array of filenames, paths to get rid of empty upload fields
$FILEPATHS = $FILENAMES = array();
foreach ($_FILES['code']['tmp_name'] as $fileid => $tmpname) {
    if (!empty($tmpname)) {
        checkFileUpload($_FILES['code']['error'][$fileid]);
        $FILEPATHS[] = $_FILES['code']['tmp_name'][$fileid];
        $FILENAMES[] = $_FILES['code']['name'][$fileid];
    }
}

// FIXME: the following checks are also performed inside
// submit_solution.

/* Determine the problem */
$probid = @$_POST['probid'];
$prob = $DB->q('MAYBETUPLE SELECT probid, name FROM problem
                INNER JOIN contestproblem USING (probid)
                WHERE allow_submit = 1 AND probid = %i AND cid = %i',
               $probid, $cid);

if (! isset($prob)) {
    err("Unable to find problem p$probid");
    return;
}
$probid = $prob['probid'];

/* Determine the language */
$langid = @$_POST['langid'];
$lang = $DB->q('MAYBETUPLE SELECT langid, name, require_entry_point, entry_point_description
                FROM language
                WHERE langid = %s AND allow_submit = 1', $langid);

if (! isset($lang)) {
    err("Unable to find language '$langid'");
    return;
}
$langid = $lang['langid'];

$entry_point = null;
if ($lang['require_entry_point']) {
    if (empty($_POST['entry_point'])) {
        $ep_desc = ($lang['entry_point_description'] ? : 'Entry point');
        err("$ep_desc required, but not specified.");
        return;
    }
    $entry_point = $_POST['entry_point'];
}

$sid = submit_solution($teamid, (int)$probid, $cid, $langid, $FILEPATHS, $FILENAMES, null, $entry_point);

auditlog('submission', $sid, 'added', 'via teampage', null, $cid);

header('Location: index.php?submitted=' . urlencode((string)$sid));