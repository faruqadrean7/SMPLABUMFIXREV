package id.sch.labschool.sistlabum.core.navigation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import id.sch.labschool.sistlabum.features.auth.LoginScreenPlaceholder
import id.sch.labschool.sistlabum.features.guardian.GuardianHomePlaceholder
import id.sch.labschool.sistlabum.features.teacher.TeacherHomePlaceholder
import id.sch.labschool.sistlabum.features.student.StudentHomePlaceholder
import id.sch.labschool.sistlabum.features.cbt.CbtTokenPlaceholder

@Composable
fun AppNavGraph(
    navController: NavHostController = rememberNavController()
) {
    NavHost(
        navController = navController,
        startDestination = Screen.Login.route
    ) {
        // 1. Auth & Common
        composable(Screen.Login.route) {
            LoginScreenPlaceholder(
                onLoginSuccess = { role ->
                    when (role) {
                        "guardian" -> navController.navigate(Screen.GuardianHome.route)
                        "teacher" -> navController.navigate(Screen.TeacherHome.route)
                        "student" -> navController.navigate(Screen.StudentHome.route)
                        else -> navController.navigate(Screen.Login.route)
                    }
                }
            )
        }

        // 2. Guardian (Ortu)
        composable(Screen.GuardianHome.route) {
            GuardianHomePlaceholder()
        }

        // 3. Teacher (Guru)
        composable(Screen.TeacherHome.route) {
            TeacherHomePlaceholder()
        }

        // 4. Student (Siswa)
        composable(Screen.StudentHome.route) {
            StudentHomePlaceholder(
                onStartCbt = { navController.navigate(Screen.CbtToken.route) }
            )
        }

        // 5. CBT
        composable(Screen.CbtToken.route) {
            CbtTokenPlaceholder()
        }
    }
}
