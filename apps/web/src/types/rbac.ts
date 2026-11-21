export interface PermissionItem {
  id: string;
  code: string;
  description: string;
  created_at?: string;
  updated_at?: string;
}

export interface RoleItem {
  id: string;
  code: string;
  name: string;
  is_system: boolean;
  permissions_count?: number;
  users_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface RoleDetail extends RoleItem {
  permissions: PermissionItem[];
}

export interface CreateRolePayload {
  code: string;
  name: string;
  permission_ids?: string[];
}

export interface UpdateRolePayload {
  name?: string;
  permission_ids?: string[];
}

export interface UserRoleItem {
  id: string;
  code: string;
  name: string;
  scopeClassId?: string | null;
  permissions?: PermissionItem[];
}

export interface UserRoleDetail {
  userId: string;
  username: string;
  roles: UserRoleItem[];
}

export interface AssignUserRolesItem {
  role_id: string;
  scope_class_id?: string | null;
}

export interface AssignUserRolesPayload {
  roles: AssignUserRolesItem[];
}
