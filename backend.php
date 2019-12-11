<?php

function saveGradeChanges($conn) {
    $edits = json_decode($_POST["edits"], true);
    foreach ($edits as $edit) {
        $stmt = $conn->prepare("UPDATE `response` SET `grade`=?, `comments`=? WHERE `questionId`=? AND `username`=? AND `examId`=?");
        $stmt->bind_param("isisi", $edit["grade"], $edit["comments"], $edit["questionId"], $_POST["username"], $_POST["id"]);
        $stmt->execute();
        $stmt->close();
    }
}

function getStudentExam($conn) {
    $stmt = $conn->prepare("SELECT `id`,`description`,`grade`,`answer`,`comments`,`points` FROM `response` INNER JOIN (`question`,`exam_question`) ON (`question`.`id`=`response`.`questionId` AND `exam_question`.`questionId`=`question`.`id` AND `exam_question`.`examId`=`response`.`examId`) WHERE `username`=? AND `response`.`examId`=?");
    $stmt->bind_param("si", $_POST["username"], $_POST["id"]);
    $stmt->execute();
    $stmt->bind_result($id, $description, $grade, $answer, $comments, $maxGrade);

    $questionList = [];
    $totalGrade = 0;
    while ($stmt->fetch()) {
        $questionList[] = [ "id" => $id, "description" => $description, "grade" => $grade, "maxGrade" => $maxGrade, "answer" => $answer, "comments" => $comments ];
        $totalGrade += $grade;
    }

    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode([ "questions" => $questionList, "grade" => $totalGrade]);
}

function submitAnswers($conn) {
    $responses = json_decode($_POST["responses"], true);
    foreach ($responses as $response) {
        $stmt = $conn->prepare("SELECT `name`, `constraint`, `points` FROM `exam_question` INNER JOIN `question` ON `question`.`id`=`exam_question`.`questionId` WHERE `examId`=? AND `questionId`=?");
        $stmt->bind_param("ii", $_POST["id"], $response["questionId"]);
        $stmt->execute();
        $stmt->bind_result($name, $constraint, $maxScore);
        $stmt->fetch();
        $stmt->close();

        $question = [ "maxScore" => $maxScore, "constraint" => $constraint, "name" => $name, "response" => $response["answer"] ];
        
        $stmt = $conn->prepare("SELECT `input`, `output` FROM `testcase` WHERE `questionId`=?");
        $stmt->bind_param("i", $response["questionId"]);
        $stmt->execute();
        $stmt->bind_result($input, $output);

        $testcases = [];
        while ($stmt->fetch()) {
            $testcases[] = [ "input" => $input, "output" => $output ];
        }
        $stmt->close();

        $question["testcases"] = $testcases;
        $payload = [ "action" => "grade", "question" => $question ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://web.njit.edu/~cb283/cs490/middle.php');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $r = curl_exec($ch);
        curl_close($ch);
        
        $graderResult = json_decode($r, true);

        $stmt = $conn->prepare("INSERT INTO response(examId, questionId, username, answer, grade, comments) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iissis", $_POST["id"], $response["questionId"], $_POST["username"], $response["answer"], $graderResult["grade"], $graderResult["comments"]);
        $stmt->execute();
        $stmt->close();
    }
}

function getExamQuestions($conn) {
    $stmt = $conn->prepare("SELECT `id`, `description` FROM `exam_question` INNER JOIN `question` ON `question`.`id`=`exam_question`.`questionId` WHERE `examId`=?");
    $stmt->bind_param("i", $_POST["id"]);
    $stmt->execute();
    $stmt->bind_result($id, $description);

    $questionList = [];
    while ($stmt->fetch()) {
        $questionList[] = [ "id" => $id, "description" => $description ];
    }

    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([ "questions" => $questionList ]);
}

function getStudentExams($conn) {
    $result = $conn->query("SELECT * FROM `exam`");
    $resultList = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $resultList[] = $row;
        }
    }

    $examList = [];
    foreach ($resultList as $exam) {
        if ($exam["released"]) {
            // Get grade for this exam
            $stmt = $conn->prepare("SELECT SUM(`grade`) FROM `response` WHERE `username`=? AND `examId`=?");
            $stmt->bind_param("si", $_POST["username"], $exam["id"]);
            $stmt->execute();
            $stmt->bind_result($grade);
            $stmt->fetch();
            $stmt->close();
        } else {
            // Check if this exam was completed
            $stmt = $conn->prepare("SELECT * FROM `response` WHERE `username`=? AND `examId`=?");
            $stmt->bind_param("si", $_POST["username"], $exam["id"]);
            $stmt->execute();
            $completed = false;
            if ($stmt->fetch())
                $completed = true;
            $stmt->close();
            if ($completed)
                $grade = "Pending grade";
            else
                $grade = "N/A";;
        }
        
        $exam["completed"] = $completed;
        $exam["grade"] = $grade;
        $examList[] = $exam;
    }
    
    header('Content-Type: application/json');
    echo json_encode([ "exams" => $examList ]);
}

function getStudentNames($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT `username` FROM `response` WHERE `examId`=? ORDER BY `username`");
    $stmt->bind_param("i", $_POST["id"]);
    $stmt->execute();
    $stmt->bind_result($username);

    $studentList = [];
    while ($stmt->fetch()) {
        $studentList[] = $username;
    }

    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode([ "students" => $studentList ]);
}

function createExam($conn) {
    $exam = json_decode($_POST["exam"], true);

    $stmt = $conn->prepare("INSERT INTO exam(`name`,`released`) VALUES (?,0)");
    $stmt->bind_param("s", $exam["name"]);
    $stmt->execute();
    $examId = $stmt->insert_id;
    $stmt->close();

    foreach ($exam["questions"] as $question) {
        $stmt = $conn->prepare("INSERT INTO exam_question(`examId`,`questionId`,`points`) VALUES (?,?,?)");
        $stmt->bind_param("iii", $examId, $question["id"], $question["points"]);
        $stmt->execute();
        $stmt->close();
    }
}

function releaseExam($conn) {
    $stmt = $conn->prepare("UPDATE `exam` SET `released`=1 WHERE `id`=?");
    $stmt->bind_param("i", $_POST["id"]);
    $stmt->execute();
    $stmt->close();
}

function getExams($conn) {
    $result = $conn->query("SELECT * FROM `exam`");
    $resultList = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $resultList[] = $row;
        }
    }
    $result = $conn->query("SELECT COUNT(`username`) AS numOfStudents FROM `users` WHERE `role`='student'");
    $students = $result->fetch_assoc()["numOfStudents"];

    $examList = [];
    foreach ($resultList as $exam) {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT `username`) FROM `response` WHERE `examId`=?");
        $stmt->bind_param("i", $exam["id"]);
        $stmt->execute();
        $stmt->bind_result($completed);
        $stmt->fetch();
        $stmt->close();

        if ($completed) {
            $stmt = $conn->prepare("SELECT FLOOR(SUM(average)) FROM (SELECT AVG(grade) AS average FROM response NATURAL JOIN exam_question WHERE grade IS NOT NULL AND examId=".$exam["id"]." GROUP BY questionId) AS T");
            $stmt->bind_param("i", $exam["id"]);
            $stmt->execute();
            $stmt->bind_result($average);
            $stmt->fetch();
            $stmt->close();
        } else {
            $average = "N/A";
        }
        
        $exam["completed"] = $completed;
        $exam["average"] = $average;
        $exam["total"] = $students;
        $examList[] = $exam;
    }
        
    
    header('Content-Type: application/json');
    echo json_encode([ "exams" => $examList ]);
}

function getQuestions($conn) {
    $result = $conn->query("SELECT * FROM `question`");

    $questionList = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $questionList[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([ "questions" => $questionList ]);
}

function addQuestion($conn) {
    $question = json_decode($_POST["question"], true);
    
    $stmt = $conn->prepare("INSERT INTO `question`(`name`,`difficulty`,`description`,`topic`,`constraint`) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $question["name"], $question["difficulty"], $question["description"], $question["topic"], $question["constraint"]);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    $sql = '';
    $testcases = $question["testcases"];
    foreach ($testcases as $testcase) {
        $input = str_replace("'", "''", $testcase["input"]);
        $output = str_replace("'", "''", $testcase["output"]);
        $sql .= "INSERT INTO `testcase`(`questionId`, `input`, `output`) VALUES (".$id.",'".$input."','".$output."');";
    }
    $conn->multi_query($sql);
}

function authenticate($conn) {
    if (isset($_POST["username"]) && !empty($_POST["username"]) && isset($_POST["password"]) && !empty($_POST["password"])) {
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE username=? AND pass=?");
        $stmt->bind_param("ss", $_POST["username"], hash("sha512", $_POST["password"]));
        $stmt->execute();
        $stmt->bind_result($username, $role);
        
        if ($stmt->fetch()) {
            $status = "success";
        } else {
            $msg = "Username and password are incorrect";
            $status = "failure";
        }
        
        header('Content-Type: application/json');
        echo json_encode([ "status" => $status, "message" => $msg, "role" => $role, "username" => $username ]);
        
        $stmt->close();
    } else {
        header("HTTP/1.1 400 Bad Request");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"]) && !empty($_POST["action"])) {
        $conn = new mysqli("sql2.njit.edu", "cb283", "tJ8YOsDYk", "cb283");
    
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $action = $_POST["action"];
        $action($conn);

        $conn->close();
    } else {
        header("HTTP/1.1 400 Bad Request");
    }
} else {
    header("HTTP/1.1 405 Method Not Allowed");
}

exit();

?>
