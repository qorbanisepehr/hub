<?php

namespace App\Domains\Employee\Controllers;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\StoreEmployeeRequest;
use App\Domains\Employee\Requests\UpdateEmployeeRequest;
use App\Domains\Employee\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController
{
    public function index(): AnonymousResourceCollection
    {
        $employees = Employee::with(['user'])->latest()->paginate(20);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $employee = Employee::create($request->validated());
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['message' => __('employee.deleted')]);
    }
}
