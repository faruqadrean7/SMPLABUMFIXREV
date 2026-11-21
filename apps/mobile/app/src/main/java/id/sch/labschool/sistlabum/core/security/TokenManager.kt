package id.sch.labschool.sistlabum.core.security

/**
 * Placeholder for secure token management using EncryptedSharedPreferences / Android Keystore.
 * Will be populated upon instruction.
 */
interface TokenManagerPlaceholder {
    fun getAccessToken(): String?
    fun getRefreshToken(): String?
    fun saveTokens(accessToken: String, refreshToken: String)
    fun clearTokens()
}
