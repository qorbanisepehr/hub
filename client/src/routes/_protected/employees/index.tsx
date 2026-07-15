import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeesPage } from "@/features/employees/pages/employees-page";
import { requirePermission } from "@/features/auth/guards";

const employeesSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    status: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees",
    validateSearch: employeesSearchSchema,
    beforeLoad: requirePermission(["employee.view_own", "employee.view_all"]),
    component: EmployeesPage,
});
