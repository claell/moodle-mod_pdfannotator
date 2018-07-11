<?php
/**
 * @package   mod_pdfannotator
 * @copyright 2018 RWTH Aachen, Anna Heynkes (see README.md)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$action = optional_param('action', 'view', PARAM_TEXT); // default action is 'view'

$taburl = new moodle_url('/mod/pdfannotator/view.php', array('id' => $id));

$myrenderer = $PAGE->get_renderer('mod_pdfannotator');

/* * ********************************************** Display overview page *********************************************** */

if ($action === 'overview') {
    
    require_once($CFG->dirroot . '/mod/pdfannotator/classes/output/overview.php');

    $PAGE->set_title("overview");

    // 1.1 Display tab navigation
    echo $myrenderer->pdfannotator_render_tabs($taburl, $action, $pdfannotator->name);
    
    // 1.2 Give javascript (see below) access to the language string repository
    $stringman = get_string_manager();
    $strings = $stringman->load_component_strings('pdfannotator', 'en'); // method gets the strings of the language files   
    $PAGE->requires->strings_for_js(array_keys($strings), 'pdfannotator'); //method to use the language-strings in javascript
    
    // 1.3 Add the javascript file that determines the dynamic behaviour of the page
    $PAGE->requires->js(new moodle_url("/mod/pdfannotator/shared/overview.js?ver=00001"));

    // 1.4 Check user capabilities to view the different categories
    $viewreports = has_capability('mod/pdfannotator:viewreports', $context, null, false); // false disregards administrator's magical 'doanything' power
    $viewanswers = has_capability('mod/pdfannotator:viewanswers', $context, null, false);
    $viewquestions = has_capability('mod/pdfannotator:viewquestions', $context, null, false);
    $viewposts = has_capability('mod/pdfannotator:viewposts', $context, null, false);
//    $viewadministration = has_capability('mod/pdfannotator:viewadministration', $context);
          
    $params = array($pdfannotator->id);
    $PAGE->requires->js_init_call('startOverview',$params,true); // 1. Name der JS-Funktion, 2. Parameter
     
    // 1.5 Create a renderer, let it render the renderable/templatable 'overview' (pass on user capabilities)
    $myrenderer = $PAGE->get_renderer('mod_pdfannotator');
    echo $myrenderer->render_overview_page(new overview($pdfannotator->id, $course->id, $pdfannotator->newsspan, $viewreports, $viewanswers, $viewquestions, $viewposts));
}

/* * ********************************************** Display the pdf in its editor (default action) *********************************************** */

if ($action === 'view') { // default
    $PAGE->set_title("annotatorview");
    echo $myrenderer->pdfannotator_render_tabs($taburl, $action, $pdfannotator->name);
//    echo $OUTPUT->heading(format_string($pdfannotator->name), 2);

    pdfannotator_display_embed($pdfannotator, $cm, $course, $file, $page, $annoid, $commid);
}

/* * ********************************************** Display statistics *********************************************** */

if ($action === 'statistic') {
    require_once($CFG->dirroot . '/mod/pdfannotator/model/statistics.class.php');
    require_once($CFG->dirroot . '/mod/pdfannotator/classes/output/statistics.php');

    echo $myrenderer->pdfannotator_render_tabs($taburl, $action, $pdfannotator->name);
    $PAGE->set_title("statisticview");
    echo $OUTPUT->heading(get_string('activities', 'pdfannotator'));
    
  // Give javascript (see below) access to the language string repository
    $stringman = get_string_manager();
    $strings = $stringman->load_component_strings('pdfannotator', 'en'); //with this method you get the strings of the language files
    $PAGE->requires->strings_for_js(array_keys($strings), 'pdfannotator'); //method to use the language-strings in javascript
    $PAGE->requires->js(new moodle_url("/mod/pdfannotator/shared/statistic.js"));
    $myrenderer = $PAGE->get_renderer('mod_pdfannotator');

    $isteacher = has_capability('mod/pdfannotator:viewreports', $context);
    echo $myrenderer->render_statistic(new statistics($cm->instance, $course->id, $isteacher)); // parameter 'statistics' is a renderable/templatable
}

/************************************************ Display form for reporting a comment  ************************************************/

if ($action === 'report') {
    
    require_once($CFG->dirroot.'/mod/pdfannotator/reportform.php');
    require_once($CFG->dirroot.'/mod/pdfannotator/model/comment.class.php');
    require_once($CFG->dirroot.'/mod/pdfannotator/model/report.class.php');
    
    // Get comment id
    $commentid = optional_param('commentid', 0, PARAM_INT);
    
    // Contextual data to pass on to the report form
    $data = new stdClass();    
        $data->course = $cm->course;
        $data->pdfannotatorid = $cm->instance;
        $data->pdfname = $cm->name;
        $data->commentid = $commentid;
        $data->id = $id; // course module id
        $data->action = 'report';
    
    // Initialise mform and pass on $data-object to it
    $mform = new pdfannotator_reportform();
        $mform->set_data($data);
    
    // -------------------------- Form processing and displaying is done here --------------------------
    if ($mform->is_cancelled()) {
        $action = 'view';
        echo $myrenderer->pdfannotator_render_tabs($taburl, $action, $pdfannotator->name);
        pdfannotator_display_embed($pdfannotator, $cm, $course, $file);

    } else if ($report = $mform->get_data()) { //In this case you process validated data. $mform->get_data() returns data posted in form.
        
        global $DB, $USER;
        
        // 1. notify course manager(s)       
        $recipients = get_enrolled_users($context, 'mod/pdfannotator:administrateuserinput');
        $name = 'newreport';
        $report->reportinguser = fullname($USER);
        $report->url = $CFG->wwwroot.'/mod/pdfannotator/view.php?id='.$cm->id.'&action=overview';
        $messagetext = new stdClass();
        $messagetext->text = format_notification_message_text($course,$cm,$context,get_string('modulename','pdfannotator'),$cm->name,$report,'reportadded');
        $messagetext->html = format_notification_message_html($course,$cm,$context,get_string('modulename','pdfannotator'),$cm->name,$report,'reportadded');
        $messagetext->url = $report->url;
        try {
            foreach ($recipients as $recipient) {
                $messageid = pdfannotator_notify_manager($recipient, $course, $cm, $name, $messagetext);
            }
        } catch (Exception $ex) {
            
        }
        
        // 2. save report in db
        $record = new stdClass();
        $record->commentid = $report->commentid;
        $record->courseid = $cm->course;
        $record->pdfannotatorid = $cm->instance;      
        $record->message = $report->introduction;
        $record->userid = $USER->id;
        $record->timecreated = time();
        $record->seen = 0;
        
        $DB->insert_record('pdfannotator_reports', $record, $returnid=true, $bulk=false);
        
        // 2. notify the reporting user that their report has been sent off (display blue toast box at top of page)
        \core\notification::info(get_string('reportwassentoff', 'pdfannotator'));
        
        $action = 'view';
        echo $myrenderer->pdfannotator_render_tabs($taburl, $action, $pdfannotator->name);
        pdfannotator_display_embed($pdfannotator, $cm, $course, $file);
        

    } else { // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
             // or on the first display of the form.
        
        $PAGE->set_title("reportform");
        echo $OUTPUT->heading(get_string('titleforreportcommentform', 'pdfannotator'));
        
            // Get information about the comment to be reported
        $comment = new pdfannotator_comment($commentid);
            $info = pdfannotator_comment_info::make_from_comment($comment);

        // Display it in a table
        $myrenderer = $PAGE->get_renderer('mod_pdfannotator');
        echo $myrenderer->render_pdfannotator_comment_info($info);

        // Now display the complaint form itself
        $mform->display();
        
    }
    
    return;
}  