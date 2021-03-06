<?php

/**
 * @Func:       課程階層管理介面
 * @License:    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @Author:     Thoomas Tsai , Ceasar Sun
 * @Note:                     
 *
*/

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('lib.php');

$site = get_site();
$PAGE->set_pagelayout('standard');
require_login();

$context = context_system::instance();
require_capability('local/ucard:view', $context);
if ((is_ucard_teacher($USER->id) == false) && (is_siteadmin() == false)){
    $context = context_system::instance();
    require_capability('local/ucard:change', $context);
}

global $CFG;
global $DB;

$PAGE->set_context($context); 
$PAGE->set_heading($site->fullname);
$PAGE->set_url(new moodle_url('/local/ucard/index.php'));
$PAGE->set_title(get_string('courseleveltitle', 'local_ucard')); 

$navbar = init_ucard_nav($PAGE);
echo $OUTPUT->header(); 
echo $OUTPUT->skip_link_target();
$table = "courselevel";
$courses = get_courses();
$rs = $DB->get_records($table);
$course_level_rs = array();
$num = 0;
foreach ($rs as $level_rs) {
    $course_level_rs[$num]['id'] = $level_rs->id;
    $course_level_rs[$num]['courseid'] = $level_rs->courseid;
    $course_level_rs[$num]['level'] = $level_rs->level;
    $course_level_rs[$num]['location'] = $level_rs->location;
    $num++;
}

$table_html = new flexible_table('Course Level Setup');

$table_html->define_baseurl(new moodle_url("/local/ucard/index.php"));
$table_html->define_columns(array('location', 'track', 'coursename', 'level'));
$table_html->define_headers(array(
	    get_string('location', 'local_ucard')." -> ".get_string('track', 'local_ucard'),
	    get_string('course', 'local_ucard'),
	    get_string('level', 'local_ucard'),
	    get_string('change', 'local_ucard')
));
/*
$table_html->define_headers(array(
	    'location -> track',
	    'course',
	    'level',
	    'change'
));
*/
$table_html->sortable(true);
$table_html->setup();
$last_color="#f9f9f9";
$last_category="";
foreach ($courses as $course){
    $category = recursivecategorynamebyid($course->category);
    $location = getlocation($course->category);
    $curlocation = 0;
    //$category = categorynamebyid($course->category);
    $categoryid = $course->category;
    if ($categoryid == 0){
	continue;
    }

    $name = $course->fullname." - ".$course->shortname;
    $name_url = new moodle_url('/course/view.php', array('id'=>$course->id));
    $name_html = "<a href=\"$name_url\">$name</a>";
    $levelrecord = "";
    $level = -1;
    foreach ($course_level_rs as $record) {
	if ($record['courseid'] === $course->id){
	    $level = $record['level'];
	    $curlocation = $record['location'];
	    $curid = $record['id'];
	    break;
	}
    }
    if (($curlocation != $location) && ($level != -1)){
	$DB->update_record($table, array('id'=>$curid, 'location'=>$location));
    }
    if ($level == -1){
	$DB->insert_record($table,array('courseid'=>$course->id, 'level'=>0, 'location'=>$location));
	$level = 0;
    }

    if($last_category != $course->category){
	if ($last_color == 'white'){
	    $last_color = '#f9f9f9';
	}else if($last_color == '#f9f9f9'){
	    $last_color = 'white';
	}
	$last_category = $course->category;
    }
    $table_html->column_style_all('background-color', $last_color);
    $editlevelurl = new moodle_url('/local/ucard/edit.php', array('category'=>$categoryid));
    $editlink = "<a href=\"$editlevelurl\" \"title=change\">".get_string('change', 'local_ucard')."</a>";
    $table_html->add_data(array($category, $name_html, $level, $editlink));
}
$table_html->print_html();
// delete removed course level
foreach ($course_level_rs as $record_rs) {
    $course = $DB->get_record('course', array('id' => $record_rs['courseid']), '*');
    if (empty($course->id)){
	$DB->delete_records($table, array('courseid'=>$record_rs['courseid']));
    }
}

echo $OUTPUT->footer();
