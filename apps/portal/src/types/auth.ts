export type UserRole =
  | 'student'
  | 'teacher'
  | 'homeroom'
  | 'counselor'
  | 'guardian'
  | 'facility_staff'
  | 'librarian'
  | 'admin_tu'
  | 'student_affairs'
  | 'public_relations'
  | 'principal'
  | 'super_admin'
  | 'guest'
  | string;

export interface UserProfile {
  type?: string;
  id?: string;
  name?: string;
  nis?: string;
  nisn?: string;
  gender?: string;
  status?: string;
  className?: string;
  classId?: string;
  nip?: string;
  phone?: string | null;
  photo_file_id?: string | null;
  photo_url?: string | null;
  photoUrl?: string | null;
  [key: string]: unknown;
}

export interface AuthUser {
  id: string;
  schoolId?: string;
  username: string;
  email?: string;
  phone?: string | null;
  status: string;
  mustChangePassword?: boolean;
  lastLoginAt?: string | null;
  roles: UserRole[];
  permissions: string[];
  profile?: UserProfile;
  full_name?: string;
  name?: string;
  nip?: string;
  avatar_url?: string | null;
}

export interface AuthTokens {
  accessToken: string;
  tokenType: string;
  expiresIn: number;
  refreshToken?: string;
}

export interface LoginResponseData {
  user: AuthUser;
  tokens: AuthTokens;
}

export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
  error?: {
    code: string;
    message: string;
    details?: Array<{ field: string; message: string }>;
    requestId?: string;
  };
}
