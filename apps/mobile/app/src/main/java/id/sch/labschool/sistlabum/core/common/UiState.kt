package id.sch.labschool.sistlabum.core.common

sealed interface UiState<out T> {
    data object Idle : UiState<Nothing>
    data object Loading : UiState<Nothing>
    data class Success<out T>(val data: T) : UiState<T>
    data class Error(val message: String, val code: String? = null) : UiState<Nothing>
    data object Empty : UiState<Nothing>
}
