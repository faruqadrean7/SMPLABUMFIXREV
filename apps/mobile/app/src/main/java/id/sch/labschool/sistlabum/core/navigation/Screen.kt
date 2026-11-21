package id.sch.labschool.sistlabum.core.navigation

/**
 * Route registry for all 36 screens in SISTLABUM Mobile.
 */
sealed class Screen(val route: String) {
    // 1. Common & Auth
    data object Splash : Screen("splash")
    data object Login : Screen("login")
    data object ForgotPassword : Screen("forgot_password")
    data object ChangePassword : Screen("change_password")
    data object SelectRole : Screen("select_role")
    data object Notifications : Screen("notifications")
    data object Announcements : Screen("announcements")
    data object Calendar : Screen("calendar")
    data object Profile : Screen("profile")

    // 2. Guardian (Ortu)
    data object GuardianHome : Screen("guardian/home")
    data object GuardianAttendance : Screen("guardian/attendance")
    data object GuardianGrades : Screen("guardian/grades")
    data object GuardianProgressReport : Screen("guardian/progress_report")
    data object GuardianSemesterReport : Screen("guardian/semester_report")
    data object GuardianLeaveRequests : Screen("guardian/leave_requests")
    data object GuardianPoints : Screen("guardian/points")
    data object GuardianContactTeacher : Screen("guardian/contact_teacher")

    // 3. Teacher (Guru)
    data object TeacherHome : Screen("teacher/home")
    data object TeacherSchedule : Screen("teacher/schedule")
    data object TeacherAttendanceSession : Screen("teacher/attendance_session")
    data object TeacherGrades : Screen("teacher/grades")
    data object TeacherCharacterNotes : Screen("teacher/character_notes")
    data object TeacherLeaveApprovals : Screen("teacher/leave_approvals")
    data object TeacherCbtGrading : Screen("teacher/cbt_grading")
    data object TeacherFacilityBooking : Screen("teacher/facility_booking")

    // 4. Student (Siswa)
    data object StudentHome : Screen("student/home")
    data object StudentSchedule : Screen("student/schedule")
    data object StudentDigitalCard : Screen("student/digital_card")
    data object StudentGrades : Screen("student/grades")
    data object StudentAssignments : Screen("student/assignments")

    // 5. CBT Mobile
    data object CbtToken : Screen("cbt/token")
    data object CbtInstruction : Screen("cbt/instruction")
    data object CbtWorkspace : Screen("cbt/workspace")
    data object CbtReview : Screen("cbt/review")
    data object CbtLocked : Screen("cbt/locked")
    data object CbtResult : Screen("cbt/result")
}
