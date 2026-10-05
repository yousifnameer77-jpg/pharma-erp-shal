"use client";

import {
  InputHTMLAttributes,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
  forwardRef,
} from "react";
import { cn } from "@/lib/cn";

interface FieldWrapProps {
  label?: string;
  error?: string;
  hint?: string;
  required?: boolean;
  className?: string;
  children: React.ReactNode;
}

export function FieldWrap({
  label,
  error,
  hint,
  required,
  className,
  children,
}: FieldWrapProps) {
  return (
    <div className={cn("flex flex-col gap-1", className)}>
      {label && (
        <label className="text-xs font-medium text-slate-700">
          {label}
          {required && <span className="text-red-500"> *</span>}
        </label>
      )}
      {children}
      {hint && !error && <span className="text-xs text-slate-400">{hint}</span>}
      {error && <span className="text-xs text-red-600">{error}</span>}
    </div>
  );
}

const baseInputClasses =
  "h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:bg-slate-50 disabled:text-slate-400";

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapClassName?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  (
    { label, error, hint, required, wrapClassName, className, ...props },
    ref,
  ) => (
    <FieldWrap
      label={label}
      error={error}
      hint={hint}
      required={required}
      className={wrapClassName}
    >
      <input
        ref={ref}
        required={required}
        className={cn(
          baseInputClasses,
          error && "border-red-400 focus:ring-red-400 focus:border-red-400",
          className,
        )}
        {...props}
      />
    </FieldWrap>
  ),
);
Input.displayName = "Input";

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapClassName?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  (
    { label, error, hint, required, wrapClassName, className, ...props },
    ref,
  ) => (
    <FieldWrap
      label={label}
      error={error}
      hint={hint}
      required={required}
      className={wrapClassName}
    >
      <textarea
        ref={ref}
        required={required}
        className={cn(
          "min-h-[72px] w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500",
          error && "border-red-400 focus:ring-red-400 focus:border-red-400",
          className,
        )}
        {...props}
      />
    </FieldWrap>
  ),
);
Textarea.displayName = "Textarea";

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapClassName?: string;
  placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  (
    {
      label,
      error,
      hint,
      required,
      wrapClassName,
      className,
      placeholder,
      children,
      ...props
    },
    ref,
  ) => (
    <FieldWrap
      label={label}
      error={error}
      hint={hint}
      required={required}
      className={wrapClassName}
    >
      <select
        ref={ref}
        required={required}
        className={cn(
          baseInputClasses,
          "appearance-none bg-no-repeat pr-8",
          error && "border-red-400",
          className,
        )}
        style={{
          backgroundImage:
            "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E\")",
          backgroundPosition: "right 0.5rem center",
          backgroundSize: "1.25em",
        }}
        {...props}
      >
        {placeholder !== undefined && <option value="">{placeholder}</option>}
        {children}
      </select>
    </FieldWrap>
  ),
);
Select.displayName = "Select";

interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ label, className, ...props }, ref) => (
    <label className="inline-flex items-center gap-2 text-sm text-slate-700">
      <input
        ref={ref}
        type="checkbox"
        className={cn(
          "h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 focus:ring-offset-0",
          className,
        )}
        {...props}
      />
      {label}
    </label>
  ),
);
Checkbox.displayName = "Checkbox";
