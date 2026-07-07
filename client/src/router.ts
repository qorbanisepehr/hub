import { createRouter } from "@tanstack/react-router";
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

const routeTree = RootRoute.addChildren([
    PublicRoute.addChildren([PublicIndexRoute]),
    LoginRoute,
    ProtectedRoute.addChildren([
        DashboardRoute,
        EmployeesRoute,
        EmployeeCreateRoute,
        EmployeeViewRoute,
        EmployeeEditRoute,
    ]),
]);

export const router = createRouter({ routeTree });

declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
