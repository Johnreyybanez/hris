<?php
$data = [
  ["title" => "Trainings completed", "url" => "usertrainings_completed.php"],
   ["title" => "Upcoming Trainings", "url" => "userupcoming_training.php"],
  ["title" => "Overtime", "url" => "user_overtime.php"],
   ["title" => "Leave", "url" => "user_leave_request.php"],
    ["title" => "Missing Time log", "url" => "user_missing_time_log.php"],
 ["title" => "Deduction", "url" => "user_deductions.php"],
  ["title" => "Profile", "url" => "user_profile.php"],
  ["title" => "Shift", "url" => "user_shift.php"],
  ["title" => "Official Business", "url" => "user_ob_request.php"],
   ["title" => "Settings", "url" => "useraccount_settings.php"],
  ["title" => "Violations", "url" => "user_violations.php"],
   ["title" => "Benefits", "url" => "user_benefits.php"],
    ["title" => "Documents", "url" => "user_documents.php"],
     ["title" => "Leave Balance", "url" => "user_leave_balance.php"],
     ["title" => "Date Time Records", "url" => "user_employeedtr.php"],
  // ➕ Add more pages here
];

header('Content-Type: application/json');
echo json_encode($data);
