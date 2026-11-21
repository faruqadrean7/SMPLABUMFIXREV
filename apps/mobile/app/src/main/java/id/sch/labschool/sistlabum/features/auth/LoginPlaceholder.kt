package id.sch.labschool.sistlabum.features.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp

@Composable
fun LoginScreenPlaceholder(
    onLoginSuccess: (String) -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "SISTLABUM",
            style = MaterialTheme.typography.headlineLarge,
            color = MaterialTheme.colorScheme.primary,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Portal Sekolah Satu Pintu (Android)",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Spacer(modifier = Modifier.height(40.dp))
        Text(
            text = "Placeholder Screen: MOB-UMM-01 (Login)",
            style = MaterialTheme.typography.bodyLarge
        )
        Spacer(modifier = Modifier.height(24.dp))
        Button(onClick = { onLoginSuccess("student") }) {
            Text("Simulasi Masuk sebagai Siswa")
        }
        Spacer(modifier = Modifier.height(8.dp))
        Button(onClick = { onLoginSuccess("guardian") }) {
            Text("Simulasi Masuk sebagai Orang Tua")
        }
        Spacer(modifier = Modifier.height(8.dp))
        Button(onClick = { onLoginSuccess("teacher") }) {
            Text("Simulasi Masuk sebagai Guru")
        }
    }
}
