import * as React from "react"

import { cn } from "@/lib/utils"

type StepperContextValue = {
  value: number
  onValueChange?: (value: number) => void
  totalSteps: number
}

const StepperContext = React.createContext<StepperContextValue | null>(null)

function useStepper() {
  const ctx = React.useContext(StepperContext)
  if (!ctx) throw new Error("Stepper components must be used within <Stepper>")
  return ctx
}

type StepperProps = React.ComponentProps<"div"> & {
  value: number
  onValueChange?: (value: number) => void
}

function Stepper({ value, onValueChange, className, children, ...props }: StepperProps) {
  const totalSteps = React.Children.count(children)

  return (
    <StepperContext.Provider value={{ value, onValueChange, totalSteps }}>
      <div
        data-slot="stepper"
        className={cn("flex flex-col gap-6", className)}
        {...props}
      >
        {children}
      </div>
    </StepperContext.Provider>
  )
}

function StepperNav({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="stepper-nav"
      className={cn("flex items-start", className)}
      {...props}
    />
  )
}

type StepperItemContextValue = {
  index: number
  state: "completed" | "active" | "inactive"
}

const StepperItemContext = React.createContext<StepperItemContextValue | null>(null)

function useStepperItem() {
  const ctx = React.useContext(StepperItemContext)
  if (!ctx) throw new Error("StepperItem sub-components must be used within <StepperItem>")
  return ctx
}

type StepperItemProps = React.ComponentProps<"div"> & {
  index: number
}

function StepperItem({ index, className, ...props }: StepperItemProps) {
  const { value } = useStepper()

  const state = index < value ? "completed" : index === value ? "active" : "inactive"

  return (
    <StepperItemContext.Provider value={{ index, state }}>
      <div
        data-slot="stepper-item"
        data-state={state}
        className={cn(
          "group/stepper-item relative flex flex-1 items-start flex-col",
          className
        )}
        {...props}
      />
    </StepperItemContext.Provider>
  )
}

function StepperTrigger({ className, ...props }: React.ComponentProps<"button">) {
  const { onValueChange } = useStepper()
  const { index } = useStepperItem()

  return (
    <button
      type="button"
      data-slot="stepper-trigger"
      onClick={() => onValueChange?.(index)}
      className={cn(
        "flex w-full grow flex-col items-start justify-center gap-3.5 outline-none focus-visible:ring-2 focus-visible:ring-ring",
        className
      )}
      {...props}
    />
  )
}

function StepperIndicator({ className, ...props }: React.ComponentProps<"div">) {
  const { state } = useStepperItem()

  return (
    <div
      data-slot="stepper-indicator"
      data-state={state}
      className={cn(
        "h-1 w-full rounded-full transition-colors",
        state === "active" && "bg-primary",
        state === "completed" && "bg-primary",
        state === "inactive" && "bg-border",
        className
      )}
      {...props}
    />
  )
}

function StepperSeparator({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="stepper-separator"
      className={cn("hidden", className)}
      {...props}
    />
  )
}

function StepperTitle({ className, ...props }: React.ComponentProps<"div">) {
  const { state } = useStepperItem()

  return (
    <div
      data-slot="stepper-title"
      data-state={state}
      className={cn(
        "text-start text-sm font-semibold",
        state === "active" && "text-foreground",
        state === "completed" && "text-foreground",
        state === "inactive" && "text-muted-foreground",
        className
      )}
      {...props}
    />
  )
}

function StepperDescription({ className, ...props }: React.ComponentProps<"div">) {
  const { state } = useStepperItem()

  return (
    <div
      data-slot="stepper-description"
      data-state={state}
      className={cn(
        "text-start text-xs text-muted-foreground",
        state === "active" && "text-muted-foreground/80",
        className
      )}
      {...props}
    />
  )
}

function StepperPanel({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="stepper-panel"
      className={cn("flex flex-col", className)}
      {...props}
    />
  )
}

type StepperContentProps = React.ComponentProps<"div"> & {
  index: number
}

function StepperContent({ index, className, children, ...props }: StepperContentProps) {
  const { value } = useStepper()

  if (index !== value) return null

  return (
    <div
      data-slot="stepper-content"
      data-state={index === value ? "active" : "inactive"}
      className={cn("animate-in fade-in-0 zoom-in-95", className)}
      {...props}
    >
      {children}
    </div>
  )
}

export {
  Stepper,
  StepperNav,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperSeparator,
  StepperTitle,
  StepperDescription,
  StepperPanel,
  StepperContent,
}
