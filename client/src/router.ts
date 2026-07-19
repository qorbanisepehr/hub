import { createRouter } from "@tanstack/react-router";
import { queryClient } from "@/lib/query-client";
import { Route as RootRoute } from "@/routes/__root";
import { Route as PublicRoute } from "@/routes/_public";
import { Route as PublicIndexRoute } from "@/routes/_public/index";
import { Route as LoginRoute } from "@/routes/login";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { Route as DashboardRoute } from "@/routes/_protected/dashboard";
import { Route as EmployeesRoute } from "@/routes/_protected/employees/index";
import { Route as EmployeeCreateRoute } from "@/routes/_protected/employees/create";
import { Route as EmployeeViewRoute } from "@/routes/_protected/employees/$id";
import { Route as EmployeeEditRoute } from "@/routes/_protected/employees/$id.edit";
import { Route as RolesRoute } from "@/routes/_protected/roles/index";
import { Route as RoleCreateRoute } from "@/routes/_protected/roles/create";
import { Route as RoleEditRoute } from "@/routes/_protected/roles/$roleId";
import { Route as UsersRoute } from "@/routes/_protected/users/index";
import { Route as UserCreateRoute } from "@/routes/_protected/users/create";
import { Route as UserViewRoute } from "@/routes/_protected/users/$userId";
import { Route as UserEditRoute } from "@/routes/_protected/users/$userId.edit";
import { Route as UserRolesRoute } from "@/routes/_protected/users/$userId.roles";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { Route as ProfileRoute } from "@/routes/_protected/profile";
import { Route as ProfileEditRoute } from "@/routes/_protected/profile.edit";
import { Route as UnauthorizedRoute } from "@/routes/_protected/unauthorized";
import { Route as CatchAllRoute } from "@/routes/_protected/$";

const routeTree = RootRoute.addChildren([
    PublicRoute.addChildren([PublicIndexRoute]),
    LoginRoute,
    ProtectedRoute.addChildren([
        DashboardRoute,
        EmployeesRoute,
        EmployeeCreateRoute,
        EmployeeViewRoute,
        EmployeeEditRoute,
        RolesRoute,
        RoleCreateRoute,
        RoleEditRoute,
        UsersRoute,
        UserCreateRoute,
        UserViewRoute,
        UserEditRoute,
        UserRolesRoute,
        SettingsRoute,
        ProfileRoute,
        ProfileEditRoute,
        UnauthorizedRoute,
        CatchAllRoute,
    ]),
]);

export const router = createRouter({
    routeTree,
    context: { queryClient },
});

declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
