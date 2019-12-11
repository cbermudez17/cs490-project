<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"]) && !empty($_POST["action"])) {
        if ($_POST["action"] == 'grade') {
            grade();
        } else {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://web.njit.edu/~cb283/cs490/backend.php');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

            $r = curl_exec($ch);
            curl_close($ch);

            header('Content-Type: application/json');
            echo $r;
        }
    } else {
        header("HTTP/1.1 400 Bad Request");
    }
} else {
    header("HTTP/1.1 405 Method Not Allowed");
}

exit();

function grade() {
    $question = $_POST["question"];

    $file = '/afs/cad/u/j/m/jmw34/public_html/490/exam.py';
    $suffix = "\nretValue = %s(%s)\nif retValue != None:\n    print(retValue)";
    $python3Path = "/afs/cad/linux/python-3.5.0_tcl-8.6.4/python/bin/python3";
    $command = escapeshellcmd($python3Path." ".$file)." 2>&1";

    $funcName = $question["name"];
    $response = $question["response"];
    $maxScore = $question["maxScore"];
    $constraint = $question["constraint"];
    $name = $question["name"];
    $testcases = $question["testcases"];
    $score = $maxScore;
    $comments = "";
    $passedTestCaseFormat = "Passed test case\n\tInput:\n\t%s\n\tOutput:\n\t%s\n";
    $failedTestCaseFormat = "-%d Failed test case\n\tInput:\n\t%s\n\tOutput:\n\t%s\n\tExpected Output:\n\t%s\n";
    $testcaseValue = intval(($maxScore - 12) / count($testcases));

    $funcNameRegex = "/^def\s+".$funcName."\(/";
    $funcNameReplaceRegex = "/^def\s+[^\(]+\(/";
    $funcColonRegex = "/^def\s+".$funcName."\([^\)]*\)\s*:/";
    $printRegex = "/\sprint\s*\(/";
    $returnRegex = "/\sreturn(\s|[\(\[\{\"])/";
    $forRegex = "/\sfor\s/";
    $whileRegex = "/\swhile\s/";

    // Check function name
    if (!preg_match($funcNameRegex, $response)) {
        $response = preg_replace($funcNameReplaceRegex, "def ".$funcName."(", $response);
        $score -= 5;
        $comments .= "-5 Missing proper function name\n";
    }

    // Check for colon in function definition
    if (!preg_match($funcColonRegex, $response)) {
        $pos = strpos($response, ")");
        $response = substr_replace($response, "):", $pos, 1);
        $score -= 3;
        $comments .= "-3 Missing colon in function definition\n";
    }

    // Check constraints
    if ($constraint == "print") {
        if (!preg_match($printRegex, $response)) {
            $score -= 4;
            $comments .= "-4 Missing print statement\n";
        }
    } else if ($constraint == "return") {
        if (!preg_match($returnRegex, $response)) {
            $score -= 4;
            $comments .= "-4 Missing return statement\n";
        }
    } else if ($constraint == "for") {
        if (!preg_match($forRegex, $response)) {
            $score -= 4;
            $comments .= "-4 Missing for loop\n";
        }
    } else {
        if (!preg_match($whileRegex, $response)) {
            $score -= 4;
            $comments .= "-4 Missing while loop\n";
        }
    }

    // Check testcases
    foreach ($testcases as $testcase) {
        $input = $testcase["input"];
        $expectedOutput = $testcase["output"];
        file_put_contents($file, $response.sprintf($suffix, $funcName, $input));
        unset($programOut);
        exec($command, $programOut, $return_var);
        if ($return_var) {
            // Error occurred
            $score -= $testcaseValue;
            $comments .= sprintf($failedTestCaseFormat, $testcaseValue, $input, end($programOut), $expectedOutput);
        } else {
            $output = trim(implode("\n", $programOut));
            if ($output != $expectedOutput) {
                $score -= $testcaseValue;
                $comments .= sprintf($failedTestCaseFormat, $testcaseValue, $input, str_replace("\n","\n\t",$output), $expectedOutput);
            } else {
                $comments .= sprintf($passedTestCaseFormat, $input, $output);
            }
        }
    }

    echo json_encode(["grade" => $score, "comments" => $comments]);
}
?>
