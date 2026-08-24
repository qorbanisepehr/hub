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
import { Route as RoleChartRoute } from "@/routes/_protected/roles/chart";
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
import { Route as QuestionnaireRoute } from "@/routes/_public/questionnaire";
import { Route as QuestionnaireIndexRoute } from "@/routes/_public/questionnaire/index";
import { Route as QuestionnaireUuidRoute } from "@/routes/_public/questionnaire/$uuid";
import { Route as CvRoute } from "@/routes/_public/cv";
import { Route as CvIndexRoute } from "@/routes/_public/cv/index";
import { Route as CvUuidRoute } from "@/routes/_public/cv/$uuid";
import { Route as CvsRoute } from "@/routes/_protected/cvs/index";
import { Route as CvBankDetailRoute } from "@/routes/_protected/cvs/$id";
import { Route as AuditRoute } from "@/routes/_protected/audit/index";
import { Route as AuditLogDetailRoute } from "@/routes/_protected/audit/$logId";
import { Route as AuditRetentionRoute } from "@/routes/_protected/audit/retention";

const routeTree = RootRoute.addChildren([
    PublicRoute.addChildren([
        PublicIndexRoute,
        QuestionnaireRoute.addChildren([
            QuestionnaireIndexRoute,
            QuestionnaireUuidRoute,
        ]),
        CvRoute.addChildren([CvIndexRoute, CvUuidRoute]),
    ]),
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
        RoleChartRoute,
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
        CvsRoute,
        CvBankDetailRoute,
        AuditRoute,
        AuditLogDetailRoute,
        AuditRetentionRoute,
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
