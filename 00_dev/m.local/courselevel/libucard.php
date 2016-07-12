<?php

class UCard {

    public  $lastError;         // Holds the last error
    public  $lastQuery;         // Holds the last query
    public  $result;            // Holds the MySQL query result
    public  $records;           // Holds the total number of records returned
    public  $affected;          // Holds the total number of records affected
    public  $rawResults;        // Holds raw 'arrayed' results
    public  $arrayedResult;     // Holds an array of the result

    private $hostname;          // MySQL Hostname
    private $username;          // MySQL Username
    private $password;          // MySQL Password
    private $database;          // MySQL Database
    private $databaseLink;      // Database Connection Link
    private $moodle_token = null;
    private $moodle_server = null;
    private $moodle_path = null;


    /* *******************
     * Class Constructor *
     * *******************/

    function __construct($database, $username, $password, $hostname='localhost', $port=3306, $persistant = false){
	$this->database = $database;
	$this->username = $username;
	$this->password = $password;
//	$this->hostname = $hostname.':'.$port;
	$this->hostname = $hostname;

	$this->Connect($persistant);
    }

    /* *******************
     * Class Destructor  *
     * *******************/

    function __destruct(){
	$this->closeConnection();
    }

    /* *******************
     * Private Functions *
     * *******************/

    // Connects class to database
    // $persistant (boolean) - Use persistant connection?
    private function Connect($persistant = false){
	$this->CloseConnection();

	$this->databaseLink = mysqli_connect($this->hostname, $this->username, $this->password);

	if(!$this->databaseLink){
	    $this->lastError = 'Could not connect to server: ' . mysqli_error($this->databaseLink);
	    return false;
	}

	if(!$this->UseDB()){
	    $this->lastError = 'Could not connect to database: ' . mysqli_error($this->databaseLink);
	    return false;
	}

	$this->setCharset(); // TODO: remove forced charset find out a specific management
	return true;
    }

    // Select database to use
    private function UseDB(){
	if(!mysqli_select_db($this->databaseLink, $this->database)){
	    $this->lastError = 'Cannot select database: ' . mysqli_error($this->databaseLink);
	    return false;
	}else{
	    return true;
	}
    }

    private function SecureData($data, $types=array()){
	if(is_array($data)){
	    $i = 0;
	    foreach($data as $key=>$val){
		if(!is_array($data[$key])){
		    $data[$key] = mysqli_real_escape_string($data[$key], $this->databaseLink);
		    $i++;
		}
	    }
	}else{
	    $data = mysqli_real_escape_string($this->databaseLink, $data);
	}
	return $data;
    }

    private function setCharset( $charset = 'UTF8' ) {
	return mysqli_set_charset ($this->databaseLink, $this->SecureData($charset,'string'));
    }

    private function commit(){
	return mysqli_query($this->databaseLink, "COMMIT");
    }

    // Closes the connections
    private function closeConnection(){
	if($this->databaseLink){
	    // Commit before closing just in case :)
	    $this->commit();
	    mysqli_close($this->databaseLink);
	}
    }

    // Executes MySQL query
    private function executeSQL($query){
	$this->lastQuery = $query;
	if($this->result = mysqli_query($this->databaseLink, $query)){
	    //var_dump($this->result);
	    if ($this->result == TRUE) {
		$this->records  = @mysqli_num_rows($this->result);
	    } else {
		$this->records  = 0;
	    }
	    $this->affected = @mysqli_affected_rows($this->databaseLink);
	    if($this->records > 0){
		$this->arrayResults();
		return $this->arrayedResult;
	    }else{
		return NULL;
		#return true;
	    }
	}else{
	    $this->lastError = mysqli_error($this->databaseLink);
	    return false;
	}
    }

    // execute Moodle API
    private function executeMoodleAPI($api, $params){
	//var_dump($params);
	$request = xmlrpc_encode_request($api, $params, array('encoding'=>'UTF-8'));

	//var_dump($request);  // In case you want to see XML.

	$context = stream_context_create(array('http' => array(
			'method' => "POST",
			'header' => "Content-Type: text/xml",
			'content' => $request
			)));

	$path = $this->moodle_server.$this->moodle_path."/webservice/xmlrpc/server.php?wstoken=".$this->moodle_token;
	// Send XML to server and get a reply from it.
	$file = file_get_contents($path, false, $context); // $file is the reply from server.
	// Decode the reply.
	$response = xmlrpc_decode($file);

	// This is our normal exit (returning an array of user properties).
	//var_dump($response);
	return $response;

    }

    // 'Arrays' a single result
    private function arrayResult(){
	$this->arrayedResult = mysqli_fetch_assoc($this->result) or die (mysqli_error($this->databaseLink));
	return $this->arrayedResult;
    }
    // 'Arrays' multiple result
    private function arrayResults(){
	/*if($this->records == 1){
	    return $this->arrayResult();
	}
	*/
	$this->arrayedResult = array();
	while ($data = mysqli_fetch_assoc($this->result)){
	    $this->arrayedResult[] = $data;
	}
	return $this->arrayedResult;
    }
    // 'Arrays' multiple results with a key
    private function arrayResultsWithKey($key='id'){
	if(isset($this->arrayedResult)){
	    unset($this->arrayedResult);
	}
	$this->arrayedResult = array();
	while($row = mysqli_fetch_assoc($this->result)){
	    foreach($row as $theKey => $theValue){
		$this->arrayedResult[$row[$key]][$theKey] = $theValue;
	    }
	}
	return $this->arrayedResult;
    }

    /* *******************
     * Public Functions  *
     * *******************/

    public function init_moodle($token, $server, $path){
	$this->moodle_token  = $token;
	$this->moodle_server = $server;
	$this->moodle_path   = $path;
    }

    public function runSQL($query){
	$data = $this->executeSQL($query);
	return $data;
    }


    public function getStudentID($cid){
	$query = "select * from student_basic where cid=$cid";
	//$data_sid = mysqli_query($query, $this->databaseLink);
	$data = $this->executeSQL($query);
	$sid = "";
	if ($data){
	    $sid = $data['sid'];
	}else{
	    $sid="no data";
	}
	return $sid;
    }

    public function logCardID($cid, $location){

	$query = "INSERT INTO cardlog (`id`, `cid`, `location`, `dtime`) VALUES (NULL, $cid, $location, CURRENT_TIMESTAMP)";
	$data = $this->executeSQL($query);
    }

    public function listCardLogs($limit = 10){
	$query = "select * from cardlog ORDER BY dtime DESC limit $limit";
	$data = $this->executeSQL($query);
	return $data;
    }

    public function getStudentLevel($sid, $location){
	$query = "select level from student_level where sid = $sid and domain = $location";
	$data = $this->executeSQL($query);
	return $data['level'];
    }

    public function getCourseIDbylevel($level, $location){
	$query = "select courseid from course_level where level=$level and domain=$location";
	$data = $this->executeSQL($query);
	if (count($data)>0){
	    return $data['courseid'];
	}else{
	    return 0;
	}
    }

    public function logRunningCourse($moodleid, $location, $courseid, $level){

	$query = "INSERT INTO running_course (`id`, `moodleid`, `location`, `courseid`, `level`, `dtime`, `status`) VALUES (NULL, $moodleid, $location, $courseid, $level, CURRENT_TIMESTAMP, 0)";
	$data = $this->executeSQL($query);
    }

    public function getRunningCourse($moodleid, $location){

	$query = "SELECT * FROM `running_course` WHERE `moodleid`=$moodleid and `location`=$location and `status`=0";
	$data = $this->executeSQL($query);
	if (count($data)>0){
	    return array($data['courseid'], $data['level']);
	} else {
	    return null;
	}
    }

    public function upgradeCourse($moodleid, $location){

	$query = "UPDATE `running_course` SET `status` = '1' WHERE `moodleid`=$moodleid and location=$location and status=0";
	$data = $this->executeSQL($query);
    }

    public function getMoodleIDbyStudentID($sid){
	$search['key']="idnumber";
	$search['value']=$sid;
	$api='core_user_get_users';
	$params = array(array($search));
	$response = $this->executeMoodleAPI($api, $params);
	$moodleid = $response['users'][0]['id'];
	return $moodleid;
    }

    public function registCourse($moodleid, $courseid){
	$params = array(array(array('roleid'=>'5', 'userid'=>$moodleid, 'courseid'=>$courseid))); // roleid 5 is "student".
	$api='enrol_manual_enrol_users';
	$response = $this->executeMoodleAPI($api, $params);
    }

    public function getPassGrades($courseid){
	$params = (int)$courseid;
	$api='core_grades_get_grades';
	$response = $this->executeMoodleAPI($api, $params);
	$items = $response['items'];
	$grade=array();
	for ($i = 0; $i < count($items); $i++){
	    array_push($grade, $items[$i]['gradepass']);
	}
	return max($grade);

    }

    public function getUserGrades($moodleid, $courseid){
	$params = array((int)$courseid, (int)$moodleid);
	$api = "gradereport_user_get_grades_table";
	$response = $this->executeMoodleAPI($api, $params);

	if (count($response)>0){
	    $tables = $response['tables'];
	    $data = $tables[0]['tabledata'];
	    $total = array_pop($data);
	    if ($total['grade']['content'] == "-"){
		return -1;
	    }
	    return $total['grade']['content'];
	}else{
	    return -1;
	}

    }

    public function getUserCourses($moodleid){
	$params = 6;
	$api='core_enrol_get_users_courses';
	$response = $this->executeMoodleAPI($api, $params);
	$all_course = $response;
	$courses=array();
	for ($i = 0; $i < count($all_course); $i++){
	    //echo $all_course[$i]['id'];
	    //echo "\n";
	    array_push($courses, $all_course[$i]['id']);
	}
	return $courses;
    }

    public function getCoursesbyLevelLocation($level, $location){
	$params = array($location, $level);
	$api='course_level_id_by_level_location';
	$response = $this->executeMoodleAPI($api, $params);
	$all_course = $response;
	$courses=array();
	for ($i = 0; $i < count($all_course); $i++){
	    //echo $all_course[$i]['id'];
	    //echo "\n";
	    array_push($courses, $all_course[$i]['id']);
	}
	return $courses;
    }

    public function getCompletionStatus($course, $user){
	
	$params = array($course, $user);
	$api='core_completion_get_course_completion_status';
	$response = $this->executeMoodleAPI($api, $params);
	
	return $response['completionstatus']['completed'];

    }
}
?>
