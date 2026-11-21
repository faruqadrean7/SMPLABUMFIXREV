package id.sch.labschool.sistlabum

import android.app.Application
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class SistLabUmApp : Application() {
    override fun onCreate() {
        super.onCreate()
        // Initialize app-level dependencies & singletons
    }
}
