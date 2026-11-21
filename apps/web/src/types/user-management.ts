export type UserStatus = 'active' | 'inactive' | 'suspended';

export interface UserRoleSummary {
  id: string;
  code: string;
  name: string;
}

export interface UserManagementItem {
  id: string;
  school_id?: string | null;
  username: string;
  email: string | null;
  phone: string | null;
  status: UserStatus | string;
  must_change_password?: boolean;
  last_login_at?: string | null;
  roles: UserRoleSummary[];
  created_at?: string;
  profile_summary?: Record<string, unknown> | null;
}

export interface CreateUserPayload {
  username: string;
  email?: string;
  phone?: string;
  password?: string;
  roles?: string[];
  status?: UserStatus;
  must_change_password?: boolean;
}

export interface CreateUserResponseData {
  user: UserManagementItem;
  temporary_password?: string | null;
}

export interface UpdateUserPayload {
  username?: string;
  email?: string;
  phone?: string;
  status?: UserStatus;
  must_change_password?: boolean;
  roles?: string[];
}

export interface ResetPasswordPayload {
  new_password?: string;
  must_change_password?: boolean;
}

export interface ResetPasswordResponseData {
  message: string;
  temporary_password?: string | null;
  must_change_password?: boolean;
}

export interface UserQueryParams {
  page?: number;
  limit?: number;
  q?: string;
  role?: string;
  status?: string;
}
