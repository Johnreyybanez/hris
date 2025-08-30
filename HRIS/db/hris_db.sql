-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2025 at 08:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hris_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `allowancetypes`
--

CREATE TABLE `allowancetypes` (
  `allowance_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `allowancetypes`
--

INSERT INTO `allowancetypes` (`allowance_id`, `name`, `description`) VALUES
(1, 'heart attack', 'sasasa');

-- --------------------------------------------------------

--
-- Table structure for table `benefittypes`
--

CREATE TABLE `benefittypes` (
  `benefit_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `benefittypes`
--

INSERT INTO `benefittypes` (`benefit_id`, `name`, `description`) VALUES
(1, 'aA', 'SASS');

-- --------------------------------------------------------

--
-- Table structure for table `daytypes`
--

CREATE TABLE `daytypes` (
  `day_type_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `multiplier` decimal(4,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daytypes`
--

INSERT INTO `daytypes` (`day_type_id`, `name`, `description`, `multiplier`) VALUES
(1, 'heart attack', 'zZZ', 1.00),
(2, 'w', 'ewew', 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `deductiontypes`
--

CREATE TABLE `deductiontypes` (
  `deduction_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deductiontypes`
--

INSERT INTO `deductiontypes` (`deduction_id`, `name`, `description`) VALUES
(1, 'asaas', 'sasasa');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES
(2, 'NBI', 'second floor\r\n'),
(3, 'heart attack', 'sdsds');

-- --------------------------------------------------------

--
-- Table structure for table `designations`
--

CREATE TABLE `designations` (
  `designation_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `level` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `designations`
--

INSERT INTO `designations` (`designation_id`, `title`, `level`) VALUES
(1, 'modal', 'level12');

-- --------------------------------------------------------

--
-- Table structure for table `employeebenefits`
--

CREATE TABLE `employeebenefits` (
  `employee_benefit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `benefit_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeebenefits`
--

INSERT INTO `employeebenefits` (`employee_benefit_id`, `employee_id`, `benefit_id`, `amount`, `start_date`, `end_date`, `remarks`) VALUES
(5, 1, 1, 200.00, '2025-06-26', '2025-06-26', 'asasa');

-- --------------------------------------------------------

--
-- Table structure for table `employeedeductions`
--

CREATE TABLE `employeedeductions` (
  `deduction_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `deduction_type_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeedeductions`
--

INSERT INTO `employeedeductions` (`deduction_id`, `employee_id`, `deduction_type_id`, `amount`, `start_date`, `end_date`, `is_recurring`, `remarks`) VALUES
(1, 1, 1, 200.00, '2025-06-27', '2025-06-13', 1, 'asasasa');

-- --------------------------------------------------------

--
-- Table structure for table `employeedocuments`
--

CREATE TABLE `employeedocuments` (
  `document_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_name` varchar(100) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeedocuments`
--

INSERT INTO `employeedocuments` (`document_id`, `employee_id`, `document_name`, `document_type`, `file_path`, `uploaded_at`, `remarks`) VALUES
(1, 1, 'pdf', 'sasa', 'uploads/documents/1751071228_Municipality of Liloan logo.png', '2025-06-26 23:38:00', '4343');

-- --------------------------------------------------------

--
-- Table structure for table `employeeemergencycontacts`
--

CREATE TABLE `employeeemergencycontacts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeeemergencycontacts`
--

INSERT INTO `employeeemergencycontacts` (`id`, `employee_id`, `name`, `relationship`, `contact_number`, `address`) VALUES
(2, 1, 'car accident', 'sister', '09094012677', 'putian');

-- --------------------------------------------------------

--
-- Table structure for table `employeegovernmentids`
--

CREATE TABLE `employeegovernmentids` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `sss_number` varchar(20) DEFAULT NULL,
  `philhealth_number` varchar(20) DEFAULT NULL,
  `pagibig_number` varchar(20) DEFAULT NULL,
  `tin_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeegovernmentids`
--

INSERT INTO `employeegovernmentids` (`id`, `employee_id`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin_number`) VALUES
(2, 1, 'sdasd', 'sds', 'dsds2', '2323');

-- --------------------------------------------------------

--
-- Table structure for table `employeeleavecredits`
--

CREATE TABLE `employeeleavecredits` (
  `leave_credit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance` decimal(5,2) DEFAULT 0.00,
  `last_updated` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employeeleaverequests`
--

CREATE TABLE `employeeleaverequests` (
  `leave_request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(4,2) DEFAULT NULL,
  `actual_leave_days` decimal(4,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeeleaverequests`
--

INSERT INTO `employeeleaverequests` (`leave_request_id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `actual_leave_days`, `reason`, `status`, `requested_at`, `approved_by`, `approved_at`, `approval_remarks`) VALUES
(1, 1, 1, '2025-06-25', '2025-07-06', 12.00, 1.00, 'dadad', 'Approved', '2025-06-25 16:44:35', 1, '2025-06-25 20:41:00', 'ewretretrt');

-- --------------------------------------------------------

--
-- Table structure for table `employeelogins`
--

CREATE TABLE `employeelogins` (
  `login_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeelogins`
--

INSERT INTO `employeelogins` (`login_id`, `employee_id`, `username`, `password_hash`, `last_login`, `is_active`) VALUES
(1, 1, 'admin', '$2y$10$vq55lifqWV59iofx2pJSh.ywqLft5BQWiwOgE1T7iDq8PPwuQwTJ.', '2025-06-30 08:40:09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employeemovements`
--

CREATE TABLE `employeemovements` (
  `movement_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `movement_type` varchar(50) DEFAULT NULL,
  `from_department_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL,
  `from_designation_id` int(11) DEFAULT NULL,
  `to_designation_id` int(11) DEFAULT NULL,
  `from_location_id` int(11) DEFAULT NULL,
  `to_location_id` int(11) DEFAULT NULL,
  `from_employment_type_id` int(11) DEFAULT NULL,
  `to_employment_type_id` int(11) DEFAULT NULL,
  `from_salary` decimal(10,2) DEFAULT NULL,
  `to_salary` decimal(10,2) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employeeofficialbusiness`
--

CREATE TABLE `employeeofficialbusiness` (
  `ob_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_from` time NOT NULL,
  `time_to` time NOT NULL,
  `purpose` text DEFAULT NULL,
  `location` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `date_regular` date DEFAULT NULL,
  `date_ended` date DEFAULT NULL,
  `total_years_service` decimal(5,2) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive','Resigned','Terminated') DEFAULT 'Active',
  `employee_number` varchar(50) DEFAULT NULL,
  `biometric_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `middle_name`, `birth_date`, `gender`, `civil_status`, `phone`, `email`, `address`, `hire_date`, `date_regular`, `date_ended`, `total_years_service`, `photo_path`, `employment_type`, `designation`, `department`, `status`, `employee_number`, `biometric_id`) VALUES
(1, 'john ', 'ybanez', 'n', '2025-06-01', 'Male', 'catholic', '09094012677', 'johnreyybanez01@gmail.com', 'putian', '2025-05-27', '2025-06-29', '2025-07-06', 1.00, 'uploads/documents1751070752_Municipality of Liloan logo.png', 'adasd', 'dsad', 'sdsds', 'Inactive', '1', '1'),
(3, 'sdd', 'dsds', 's', '2025-06-21', 'Female', 'dsd', '09094012677', 'castillojames132002@gmail.com', 'dsdsds', '2025-01-28', '2028-05-27', '2029-10-27', 2.00, 'uploads/documents1751071501_LIPSEMO.png', NULL, NULL, NULL, 'Inactive', '2', '2');

-- --------------------------------------------------------

--
-- Table structure for table `employeeseparations`
--

CREATE TABLE `employeeseparations` (
  `separation_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `separation_type` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `final_working_day` date NOT NULL,
  `clearance_status` varchar(20) DEFAULT 'Pending',
  `cleared_by` int(11) DEFAULT NULL,
  `clearance_date` date DEFAULT NULL,
  `exit_interview_notes` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employeetrainings`
--

CREATE TABLE `employeetrainings` (
  `training_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `training_category_id` int(11) DEFAULT NULL,
  `training_title` varchar(150) DEFAULT NULL,
  `provider` varchar(150) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeetrainings`
--

INSERT INTO `employeetrainings` (`training_id`, `employee_id`, `training_category_id`, `training_title`, `provider`, `start_date`, `end_date`, `remarks`) VALUES
(2, 1, 1, 'dsdsds', 'howow', '2025-06-25', '2025-06-25', 'dsdsdsd');

-- --------------------------------------------------------

--
-- Table structure for table `employeeviolations`
--

CREATE TABLE `employeeviolations` (
  `violation_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `violation_type_id` int(11) DEFAULT NULL,
  `violation_date` date DEFAULT NULL,
  `sanction_type_id` int(11) DEFAULT NULL,
  `sanction_start_date` date DEFAULT NULL,
  `sanction_end_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reported_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeeviolations`
--

INSERT INTO `employeeviolations` (`violation_id`, `employee_id`, `violation_type_id`, `violation_date`, `sanction_type_id`, `sanction_start_date`, `sanction_end_date`, `remarks`, `reported_by`) VALUES
(3, 1, 1, '2025-06-19', 1, '2025-06-05', '2025-06-06', 'dadsasd', 'john');

-- --------------------------------------------------------

--
-- Table structure for table `employmenttypes`
--

CREATE TABLE `employmenttypes` (
  `type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employmenttypes`
--

INSERT INTO `employmenttypes` (`type_id`, `name`, `description`) VALUES
(1, 'sasasa', 'sasa');

-- --------------------------------------------------------

--
-- Table structure for table `holidaycalendar`
--

CREATE TABLE `holidaycalendar` (
  `holiday_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidaycalendar`
--

INSERT INTO `holidaycalendar` (`holiday_id`, `date`, `name`, `type`) VALUES
(1, '2025-06-24', 'san juan', 'Regular Holiday');

-- --------------------------------------------------------

--
-- Table structure for table `leavecreditlogs`
--

CREATE TABLE `leavecreditlogs` (
  `log_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `change_amount` decimal(5,2) DEFAULT NULL,
  `previous_balance` decimal(5,2) DEFAULT NULL,
  `new_balance` decimal(5,2) DEFAULT NULL,
  `change_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leavepolicies`
--

CREATE TABLE `leavepolicies` (
  `policy_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `employment_type_id` int(11) NOT NULL,
  `accrual_rate` decimal(5,2) NOT NULL,
  `max_balance` decimal(5,2) DEFAULT NULL,
  `can_carry_over` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavepolicies`
--

INSERT INTO `leavepolicies` (`policy_id`, `leave_type_id`, `employment_type_id`, `accrual_rate`, `max_balance`, `can_carry_over`) VALUES
(1, 1, 1, 0.04, 0.03, 1);

-- --------------------------------------------------------

--
-- Table structure for table `leaverequestdays`
--

CREATE TABLE `leaverequestdays` (
  `id` int(11) NOT NULL,
  `leave_request_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `is_working_day` tinyint(1) DEFAULT 1,
  `is_holiday` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaverequestdays`
--

INSERT INTO `leaverequestdays` (`id`, `leave_request_id`, `leave_date`, `is_working_day`, `is_holiday`, `remarks`) VALUES
(1, 1, '2025-06-27', 1, 1, 'sasasas'),
(2, 1, '2025-06-27', 1, 1, 'sasasas');

-- --------------------------------------------------------

--
-- Table structure for table `leavetypes`
--

CREATE TABLE `leavetypes` (
  `leave_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavetypes`
--

INSERT INTO `leavetypes` (`leave_type_id`, `name`, `description`, `is_paid`) VALUES
(1, 'johnrey', 'ewewew', 0),
(2, 'johnrey', 'wewew', 1);

-- --------------------------------------------------------

--
-- Table structure for table `loantypes`
--

CREATE TABLE `loantypes` (
  `loan_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loantypes`
--

INSERT INTO `loantypes` (`loan_type_id`, `name`, `description`) VALUES
(1, 'sass', 'sass');

-- --------------------------------------------------------

--
-- Table structure for table `missingtimelogrequests`
--

CREATE TABLE `missingtimelogrequests` (
  `request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `missing_field` varchar(50) DEFAULT NULL,
  `requested_time` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `missingtimelogrequests`
--

INSERT INTO `missingtimelogrequests` (`request_id`, `employee_id`, `date`, `missing_field`, `requested_time`, `reason`, `status`, `requested_at`, `approved_by`, `approved_at`, `approval_remarks`) VALUES
(1, 1, '2025-06-25', 'time_in', '2025-06-25 22:31:00', 'sasdsad', 'Approved', '2025-06-25 21:40:41', 1, '2025-06-25 21:55:00', 'asasasasa');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('read','unread') DEFAULT 'unread',
  `is_read` enum('yes','no') DEFAULT 'no',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officelocations`
--

CREATE TABLE `officelocations` (
  `location_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officelocations`
--

INSERT INTO `officelocations` (`location_id`, `name`, `address`) VALUES
(1, 'liloan', 'dadsdsddsds');

-- --------------------------------------------------------

--
-- Table structure for table `payperiods`
--

CREATE TABLE `payperiods` (
  `period_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_day` int(11) NOT NULL,
  `end_day` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payperiods`
--

INSERT INTO `payperiods` (`period_id`, `name`, `start_day`, `end_day`) VALUES
(1, 'carron', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `performancecriteria`
--

CREATE TABLE `performancecriteria` (
  `criteria_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performancecriteria`
--

INSERT INTO `performancecriteria` (`criteria_id`, `title`, `description`) VALUES
(1, 'cinema', 'dds');

-- --------------------------------------------------------

--
-- Table structure for table `sanctiontypes`
--

CREATE TABLE `sanctiontypes` (
  `sanction_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sanctiontypes`
--

INSERT INTO `sanctiontypes` (`sanction_id`, `name`, `description`) VALUES
(1, 'car accident', 'sasa');

-- --------------------------------------------------------

--
-- Table structure for table `shiftdays`
--

CREATE TABLE `shiftdays` (
  `shift_day_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `day_of_week` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shiftdays`
--

INSERT INTO `shiftdays` (`shift_day_id`, `shift_id`, `day_of_week`) VALUES
(1, 1, 'Wednesday');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`shift_id`, `shift_name`, `start_time`, `end_time`, `description`) VALUES
(1, 'asasa', '18:48:00', '00:00:00', 'sasas');

-- --------------------------------------------------------

--
-- Table structure for table `timeattendancerules`
--

CREATE TABLE `timeattendancerules` (
  `rule_id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `threshold_minutes` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timeattendancerules`
--

INSERT INTO `timeattendancerules` (`rule_id`, `rule_name`, `rule_type`, `description`, `threshold_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Late', 'sdsdsd', 1, 1, '2025-06-27 23:33:52', '2025-06-27 23:33:52');

-- --------------------------------------------------------

--
-- Table structure for table `trainingcategories`
--

CREATE TABLE `trainingcategories` (
  `training_category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trainingcategories`
--

INSERT INTO `trainingcategories` (`training_category_id`, `name`, `description`) VALUES
(1, 'sasa', 'sasasa');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$t0FjS9Ih9JA8YMBSm0Vt5OOSpZ2UgnccL2U/N5cQ6muq2/Q3Mr/u2', 'admin'),
(2, 'admin', 'admin@example.com', '$2y$10$9AJ1HU.zNTr.Z8MfSD0crucPCuznqze/NPmqp7j.97ci4Dohq66IK', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `violationtypes`
--

CREATE TABLE `violationtypes` (
  `violation_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violationtypes`
--

INSERT INTO `violationtypes` (`violation_id`, `name`, `description`) VALUES
(1, 'heart attack', 'fcdfs');

-- --------------------------------------------------------

--
-- Table structure for table `worksuspensions`
--

CREATE TABLE `worksuspensions` (
  `suspension_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_half_day` tinyint(1) DEFAULT 0,
  `is_full_day` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `worksuspensions`
--

INSERT INTO `worksuspensions` (`suspension_id`, `date`, `name`, `description`, `is_half_day`, `is_full_day`, `start_time`, `end_time`, `location_id`, `created_at`) VALUES
(1, '2025-06-12', 'car accident', 'sasasa', 0, 1, '22:00:00', '22:00:00', 1, '2025-06-27 22:59:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allowancetypes`
--
ALTER TABLE `allowancetypes`
  ADD PRIMARY KEY (`allowance_id`);

--
-- Indexes for table `benefittypes`
--
ALTER TABLE `benefittypes`
  ADD PRIMARY KEY (`benefit_id`);

--
-- Indexes for table `daytypes`
--
ALTER TABLE `daytypes`
  ADD PRIMARY KEY (`day_type_id`);

--
-- Indexes for table `deductiontypes`
--
ALTER TABLE `deductiontypes`
  ADD PRIMARY KEY (`deduction_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `designations`
--
ALTER TABLE `designations`
  ADD PRIMARY KEY (`designation_id`);

--
-- Indexes for table `employeebenefits`
--
ALTER TABLE `employeebenefits`
  ADD PRIMARY KEY (`employee_benefit_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `benefit_id` (`benefit_id`);

--
-- Indexes for table `employeedeductions`
--
ALTER TABLE `employeedeductions`
  ADD PRIMARY KEY (`deduction_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `deduction_type_id` (`deduction_type_id`);

--
-- Indexes for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employeeemergencycontacts`
--
ALTER TABLE `employeeemergencycontacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employeegovernmentids`
--
ALTER TABLE `employeegovernmentids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employeeleavecredits`
--
ALTER TABLE `employeeleavecredits`
  ADD PRIMARY KEY (`leave_credit_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `employeeleaverequests`
--
ALTER TABLE `employeeleaverequests`
  ADD PRIMARY KEY (`leave_request_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `employeelogins`
--
ALTER TABLE `employeelogins`
  ADD PRIMARY KEY (`login_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employeemovements`
--
ALTER TABLE `employeemovements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `from_department_id` (`from_department_id`),
  ADD KEY `to_department_id` (`to_department_id`),
  ADD KEY `from_designation_id` (`from_designation_id`),
  ADD KEY `to_designation_id` (`to_designation_id`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`),
  ADD KEY `from_employment_type_id` (`from_employment_type_id`),
  ADD KEY `to_employment_type_id` (`to_employment_type_id`);

--
-- Indexes for table `employeeofficialbusiness`
--
ALTER TABLE `employeeofficialbusiness`
  ADD PRIMARY KEY (`ob_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `employeeseparations`
--
ALTER TABLE `employeeseparations`
  ADD PRIMARY KEY (`separation_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `cleared_by` (`cleared_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `employeetrainings`
--
ALTER TABLE `employeetrainings`
  ADD PRIMARY KEY (`training_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `training_category_id` (`training_category_id`);

--
-- Indexes for table `employeeviolations`
--
ALTER TABLE `employeeviolations`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `violation_type_id` (`violation_type_id`),
  ADD KEY `sanction_type_id` (`sanction_type_id`);

--
-- Indexes for table `employmenttypes`
--
ALTER TABLE `employmenttypes`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `holidaycalendar`
--
ALTER TABLE `holidaycalendar`
  ADD PRIMARY KEY (`holiday_id`);

--
-- Indexes for table `leavecreditlogs`
--
ALTER TABLE `leavecreditlogs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leavepolicies`
--
ALTER TABLE `leavepolicies`
  ADD PRIMARY KEY (`policy_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `employment_type_id` (`employment_type_id`);

--
-- Indexes for table `leaverequestdays`
--
ALTER TABLE `leaverequestdays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_request_id` (`leave_request_id`);

--
-- Indexes for table `leavetypes`
--
ALTER TABLE `leavetypes`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `loantypes`
--
ALTER TABLE `loantypes`
  ADD PRIMARY KEY (`loan_type_id`);

--
-- Indexes for table `missingtimelogrequests`
--
ALTER TABLE `missingtimelogrequests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `officelocations`
--
ALTER TABLE `officelocations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `payperiods`
--
ALTER TABLE `payperiods`
  ADD PRIMARY KEY (`period_id`);

--
-- Indexes for table `performancecriteria`
--
ALTER TABLE `performancecriteria`
  ADD PRIMARY KEY (`criteria_id`);

--
-- Indexes for table `sanctiontypes`
--
ALTER TABLE `sanctiontypes`
  ADD PRIMARY KEY (`sanction_id`);

--
-- Indexes for table `shiftdays`
--
ALTER TABLE `shiftdays`
  ADD PRIMARY KEY (`shift_day_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`shift_id`);

--
-- Indexes for table `timeattendancerules`
--
ALTER TABLE `timeattendancerules`
  ADD PRIMARY KEY (`rule_id`);

--
-- Indexes for table `trainingcategories`
--
ALTER TABLE `trainingcategories`
  ADD PRIMARY KEY (`training_category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `violationtypes`
--
ALTER TABLE `violationtypes`
  ADD PRIMARY KEY (`violation_id`);

--
-- Indexes for table `worksuspensions`
--
ALTER TABLE `worksuspensions`
  ADD PRIMARY KEY (`suspension_id`),
  ADD KEY `location_id` (`location_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allowancetypes`
--
ALTER TABLE `allowancetypes`
  MODIFY `allowance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `benefittypes`
--
ALTER TABLE `benefittypes`
  MODIFY `benefit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `daytypes`
--
ALTER TABLE `daytypes`
  MODIFY `day_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deductiontypes`
--
ALTER TABLE `deductiontypes`
  MODIFY `deduction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `designations`
--
ALTER TABLE `designations`
  MODIFY `designation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employeebenefits`
--
ALTER TABLE `employeebenefits`
  MODIFY `employee_benefit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employeedeductions`
--
ALTER TABLE `employeedeductions`
  MODIFY `deduction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employeeemergencycontacts`
--
ALTER TABLE `employeeemergencycontacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employeegovernmentids`
--
ALTER TABLE `employeegovernmentids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employeeleavecredits`
--
ALTER TABLE `employeeleavecredits`
  MODIFY `leave_credit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employeeleaverequests`
--
ALTER TABLE `employeeleaverequests`
  MODIFY `leave_request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employeelogins`
--
ALTER TABLE `employeelogins`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employeemovements`
--
ALTER TABLE `employeemovements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employeeofficialbusiness`
--
ALTER TABLE `employeeofficialbusiness`
  MODIFY `ob_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employeeseparations`
--
ALTER TABLE `employeeseparations`
  MODIFY `separation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employeetrainings`
--
ALTER TABLE `employeetrainings`
  MODIFY `training_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employeeviolations`
--
ALTER TABLE `employeeviolations`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employmenttypes`
--
ALTER TABLE `employmenttypes`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `holidaycalendar`
--
ALTER TABLE `holidaycalendar`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leavecreditlogs`
--
ALTER TABLE `leavecreditlogs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leavepolicies`
--
ALTER TABLE `leavepolicies`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leaverequestdays`
--
ALTER TABLE `leaverequestdays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leavetypes`
--
ALTER TABLE `leavetypes`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loantypes`
--
ALTER TABLE `loantypes`
  MODIFY `loan_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `missingtimelogrequests`
--
ALTER TABLE `missingtimelogrequests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `officelocations`
--
ALTER TABLE `officelocations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payperiods`
--
ALTER TABLE `payperiods`
  MODIFY `period_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `performancecriteria`
--
ALTER TABLE `performancecriteria`
  MODIFY `criteria_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sanctiontypes`
--
ALTER TABLE `sanctiontypes`
  MODIFY `sanction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shiftdays`
--
ALTER TABLE `shiftdays`
  MODIFY `shift_day_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timeattendancerules`
--
ALTER TABLE `timeattendancerules`
  MODIFY `rule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `trainingcategories`
--
ALTER TABLE `trainingcategories`
  MODIFY `training_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `violationtypes`
--
ALTER TABLE `violationtypes`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `worksuspensions`
--
ALTER TABLE `worksuspensions`
  MODIFY `suspension_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employeebenefits`
--
ALTER TABLE `employeebenefits`
  ADD CONSTRAINT `employeebenefits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeebenefits_ibfk_2` FOREIGN KEY (`benefit_id`) REFERENCES `benefittypes` (`benefit_id`);

--
-- Constraints for table `employeedeductions`
--
ALTER TABLE `employeedeductions`
  ADD CONSTRAINT `employeedeductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeedeductions_ibfk_2` FOREIGN KEY (`deduction_type_id`) REFERENCES `deductiontypes` (`deduction_id`);

--
-- Constraints for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  ADD CONSTRAINT `employeedocuments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `employeeemergencycontacts`
--
ALTER TABLE `employeeemergencycontacts`
  ADD CONSTRAINT `employeeemergencycontacts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employeegovernmentids`
--
ALTER TABLE `employeegovernmentids`
  ADD CONSTRAINT `employeegovernmentids_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employeeleavecredits`
--
ALTER TABLE `employeeleavecredits`
  ADD CONSTRAINT `employeeleavecredits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeeleavecredits_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leavetypes` (`leave_type_id`);

--
-- Constraints for table `employeeleaverequests`
--
ALTER TABLE `employeeleaverequests`
  ADD CONSTRAINT `employeeleaverequests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeeleaverequests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leavetypes` (`leave_type_id`);

--
-- Constraints for table `employeelogins`
--
ALTER TABLE `employeelogins`
  ADD CONSTRAINT `employeelogins_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `employeemovements`
--
ALTER TABLE `employeemovements`
  ADD CONSTRAINT `employeemovements_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeemovements_ibfk_2` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `employeemovements_ibfk_3` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `employeemovements_ibfk_4` FOREIGN KEY (`from_designation_id`) REFERENCES `designations` (`designation_id`),
  ADD CONSTRAINT `employeemovements_ibfk_5` FOREIGN KEY (`to_designation_id`) REFERENCES `designations` (`designation_id`),
  ADD CONSTRAINT `employeemovements_ibfk_6` FOREIGN KEY (`from_location_id`) REFERENCES `officelocations` (`location_id`),
  ADD CONSTRAINT `employeemovements_ibfk_7` FOREIGN KEY (`to_location_id`) REFERENCES `officelocations` (`location_id`),
  ADD CONSTRAINT `employeemovements_ibfk_8` FOREIGN KEY (`from_employment_type_id`) REFERENCES `employmenttypes` (`type_id`),
  ADD CONSTRAINT `employeemovements_ibfk_9` FOREIGN KEY (`to_employment_type_id`) REFERENCES `employmenttypes` (`type_id`);

--
-- Constraints for table `employeeofficialbusiness`
--
ALTER TABLE `employeeofficialbusiness`
  ADD CONSTRAINT `employeeofficialbusiness_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `employeeseparations`
--
ALTER TABLE `employeeseparations`
  ADD CONSTRAINT `employeeseparations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeeseparations_ibfk_2` FOREIGN KEY (`cleared_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `employeeseparations_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `employeetrainings`
--
ALTER TABLE `employeetrainings`
  ADD CONSTRAINT `employeetrainings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeetrainings_ibfk_2` FOREIGN KEY (`training_category_id`) REFERENCES `trainingcategories` (`training_category_id`);

--
-- Constraints for table `employeeviolations`
--
ALTER TABLE `employeeviolations`
  ADD CONSTRAINT `employeeviolations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employeeviolations_ibfk_2` FOREIGN KEY (`violation_type_id`) REFERENCES `violationtypes` (`violation_id`),
  ADD CONSTRAINT `employeeviolations_ibfk_3` FOREIGN KEY (`sanction_type_id`) REFERENCES `sanctiontypes` (`sanction_id`);

--
-- Constraints for table `leavecreditlogs`
--
ALTER TABLE `leavecreditlogs`
  ADD CONSTRAINT `leavecreditlogs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `leavecreditlogs_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leavetypes` (`leave_type_id`);

--
-- Constraints for table `leavepolicies`
--
ALTER TABLE `leavepolicies`
  ADD CONSTRAINT `leavepolicies_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leavetypes` (`leave_type_id`),
  ADD CONSTRAINT `leavepolicies_ibfk_2` FOREIGN KEY (`employment_type_id`) REFERENCES `employmenttypes` (`type_id`);

--
-- Constraints for table `leaverequestdays`
--
ALTER TABLE `leaverequestdays`
  ADD CONSTRAINT `leaverequestdays_ibfk_1` FOREIGN KEY (`leave_request_id`) REFERENCES `employeeleaverequests` (`leave_request_id`);

--
-- Constraints for table `missingtimelogrequests`
--
ALTER TABLE `missingtimelogrequests`
  ADD CONSTRAINT `missingtimelogrequests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `shiftdays`
--
ALTER TABLE `shiftdays`
  ADD CONSTRAINT `shiftdays_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`);

--
-- Constraints for table `worksuspensions`
--
ALTER TABLE `worksuspensions`
  ADD CONSTRAINT `worksuspensions_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `officelocations` (`location_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
