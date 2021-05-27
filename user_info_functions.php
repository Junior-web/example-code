function getCourseID(array $options)
{
  return select_from_bd(['course_id'], 'lessons', $options)->fetch_array(MYSQLI_ASSOC)["course_id"];
}

function getRegistrationCourse($user_id)
{
  $reg_info = select_from_bd(['registration_language', 'registration_course'], 'analitics_registration', ['user_id' => $user_id])->fetch_array(MYSQLI_ASSOC);

  return [get_course_id([
    'language' => $reg_info["registration_language"],
    'course' => $reg_info["registration_course"]
  ])];
}

function getProgressCourses($user_id)
{
  $audios = [];
  $progress_courses = [];

  $audios_query = select_from_bd(['audio_id'], 'progress_courses', ['user_id' => $user_id]);

  while ($arr = $audios_query->fetch_array(MYSQLI_ASSOC)) {
    $audios[] = $arr["audio_id"];
  }

  foreach ($audios as $audio_id) {
    $get_course_from_audio = function () use ($audio_id) {
      global $mysqli;
      return $mysqli->query("SELECT `course_id` FROM `lessons` WHERE `id_audio` LIKE '%$audio_id%'")->fetch_array(MYSQLI_ASSOC)["course_id"];
    };

    $audio = $getCourseFromAudio();

    if (!in_array($audio, $progress_courses)) {
      $progress_courses[] = $audio;
    }
  }

  return $progress_courses;
}

function getMyCourses($user_id)
{
  $my_courses = [];

  $check_in_array = function ($array) use (&$my_courses) {
    foreach ($array as $elem) {
      if (!in_array($elem, $my_courses)) $my_courses[] = $elem;
    }
  };

  $check_in_array(get_purchase_courses($user_id));
  $check_in_array(get_progress_courses($user_id));
  $check_in_array(get_registration_course($user_id));

  return $my_courses;
}

function getPurchaseCourses($user_id)
{
  $purchase_courses_query = select_from_bd(['course_id'], 'open_courses', ['user_id' => $user_id]);

  $purchase_courses = [];
  if ($purchase_courses_query->num_rows > 0) {
    while ($arr = $purchase_courses_query->fetch_array(MYSQLI_ASSOC)) {
      $purchase_courses[] = $arr["course_id"];
    }
  }

  return $purchase_courses;
}

function getPurchase($user_id)
{
  global $mysqli;

  $get_count_chapter = function ($chapter_id) use ($mysqli) {
    return $mysqli->query("SELECT count(`id`) as count FROM `lessons` WHERE `group_lesson` = '$chapter_id'")->fetch_array(MYSQLI_ASSOC)["count"];
  };

  $purchase_lessons_query = $mysqli->query("
        SELECT lo.`course_id`, lo.`group_id`, lo.`lesson_id`, crs.`level` 
        FROM `open_lessons` as lo
        LEFT JOIN courses crs ON lo.`course_id` = crs.`id`
        WHERE `user_id` = '$user_id'");

  $purchase = [];

  if ($purchase_lessons_query->num_rows > 0) {
    while ($arr = $purchase_lessons_query->fetch_array(MYSQLI_ASSOC)) {
      if ($arr["level"] === "elementary") {
        if ($getCountChapter($arr["group_id"]) - 1 == count($purchase[$arr["course_id"]][$arr["group_id"]]) + 1) {
          unset($purchase[$arr["course_id"]][$arr["group_id"]]);
          if (!in_array($arr["group_id"], $purchase[$arr["course_id"]])) {
            $purchase[$arr["course_id"]][] = $arr["group_id"];
          }
        } else {
          $purchase[$arr["course_id"]][$arr["group_id"]][] = $arr["lesson_id"];
        }
      } else {
        $purchase[$arr["course_id"]][] = $arr["lesson_id"];
      }
    }
  }

  return $purchase;
}

function getUserInfo($user_id)
{
  $user = [];
  $user_info = select_from_bd(['id', 'email', 'name', 'current_country', 'registration_date', 'avatar'], 'users', ['id' => $user_id]);
  if ($user_info->num_rows > 0) {
    $user = $user_info->fetch_array();

    if (!empty($purchase_courses = getPurchaseCourses($user_id))) {
      $user["purchase"] = $purchase_courses;
    }

    if (!empty($purchase = getPurchase($user_id))) {
      foreach ($purchase as $key => $value) {
        if (!in_array($key, $user["purchase"])) {
          $user["purchase"][$key] = $value;
        }
      }
    }

    if (!empty($my_courses = getMyCourses($user_id))) {
      $user["my_courses"] = $my_courses;
    }

    return $user;
  } else {
    return "Такого пользователя не существует";
  }
}

function getUserLessonProgress($user_id, array $options) // options: [group_lesson|course_id]
{
  $lesson_list = [];

  $lesson_list_query = select_from_bd(['id', 'id_audio'], 'lessons', $options);
  $get_type_audio = function ($audio_id) {
    return select_from_bd(['type'], 'audios', ['id' => $audio_id])->fetch_array(MYSQLI_ASSOC);
  };

  while ($arr = $lesson_list_query->fetch_array(MYSQLI_ASSOC)) {
    if (!empty($arr["id_audio"])) {
      foreach (explode(",", $arr["id_audio"]) as $audio) {
        $lesson_list[$arr["id"]][$audio]["status"] = "F";
        $lesson_list[$arr["id"]][$audio]["type"] = $get_type_audio($audio)["type"];
      }
    }
  }

  $progress_list_query = select_from_bd(['audio_id', 'status'], 'progress_courses', ['user_id' => $user_id]);

  while ($arr = $progress_list_query->fetch_array(MYSQLI_ASSOC)) {
    foreach ($lesson_list as $lesson_id => $audios) {
      foreach ($audios as $audio_id => $status) {
        if ($audio_id == $arr['audio_id']) {
          $lesson_list[$lesson_id][$audio_id]["status"] = $arr["status"];
        }
      }
    }
  }

  return $lesson_list;
}

function getUserProgress($lesson_progress)
{
  $counter = [
    "total" => 0,   // количество полностью прослушанных уроков
    "l" => [
      "completed" => 0    // прослушанное количество уроков
    ],
    "p" => [
      "total_completed" => 0    // прослушанное количество практик
    ]
  ];

  foreach ($lesson_progress as $audios) {
    $counter_audio = 0;
    $counter["p"]["summ"] = 0;
    $counter["p"]["completed"] = 0;

    foreach ($audios as $data) {
      if ($data["type"] == "p") $counter["p"]["summ"]++;

      if ($data["status"] == "S") {
        $counter_audio++;

        $counter[$data["type"]]["completed"]++;
      }

      if ($counter_audio == count($audios)) {
        $counter["total"]++;
      }
    }

    if ($counter["p"]["summ"] == $counter["p"]["completed"]) {
      $counter["p"]["total_completed"]++;
    }
  }

  return [
    "middle" => [
      "num_lessons_completed" => $counter["l"]["completed"],
      "num_practices_completed" => $counter["p"]["total_completed"],
    ],
    "totals" => [
      "total_lessons" => count(array_keys($lesson_progress)),
      "total_lessons_completed" => $counter["total"]
    ]
  ];
}

function getOpenLessons($user_id, $course_id)
{
  $open_lessons = [];
  $course_level = select_from_bd(['level'], 'courses', ['id' => $course_id])->fetch_array(MYSQLI_ASSOC)["level"];
  global $mysqli;
  
  $lessons = $mysqli->query("
    SELECT DISTINCT les.`id`, les.`group_lesson`, les.`course_id`
    FROM (
        SELECT `id`, `group_lesson`, `course_id` FROM `lessons` WHERE `availability` = 'Y'
        UNION ALL
        SELECT `lesson_id` as `id`, `group_id` as `group_lesson`, `course_id` FROM `open_lessons` WHERE `user_id` = '$user_id'
    ) as les
    WHERE les.`course_id` = '$course_id'
    ORDER BY `id` ASC
  ");

  while($arr = $lessons->fetch_array(MYSQLI_ASSOC))
  {
    if($course_level == "elementary") 
    {     
      $open_lessons[$arr["id"]] = $arr["group_lesson"]; 
    }
    else 
    {
      $open_lessons[] = $arr["id"];
    }
  }

  return $open_lessons;
}

function checkingPurchaseChapterOrCourse($options) // ["id", "email", "course_id", "chapter_id"]
{
    if(!$options) return;

    list("id" => $id, "email" => $email, "course_id" => $courseID, "chapter_id" => $chapterID) = $options;    

    function recursy(array $arr, string $chap): bool
    {
        if (array_key_exists($chap, $arr) || 
            in_array($chap, $arr, true)) {
            return true;
        }
    
        foreach($arr as $value) {
            if(is_array($value) && recursy($value, $chap)) {
                return true;
            }  
        }
    
        return false;
    }


    $check = function($id) use ($courseID, $chapterID) {
        $purchase = get_purchase($id);

        if($courseID)
        {
            return in_array($courseID, array_keys($purchase));
        }

        if($chapterID)
        {   
            return recursy($purchase, $chapterID);
        }
    };

    if(!$id)
    {
        if($email)
        {
            $id = select_from_bd(['id'], 'users', ['email' => $email])->fetch_array(MYSQLI_ASSOC)["id"];
        }
    }

    return $check($id);
}