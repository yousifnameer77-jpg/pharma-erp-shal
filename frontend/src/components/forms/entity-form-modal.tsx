"use client";

import { useState } from "react";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea, Checkbox } from "@/components/ui/field";
import { ApiError } from "@/lib/api";
import { useToast } from "@/components/ui/toast";

export type FormValue = string | number | boolean | null | undefined;

export interface FormFieldSchema {
  name: string;
  label: string;
  type?:
    | "text"
    | "number"
    | "textarea"
    | "select"
    | "checkbox"
    | "date"
    | "email";
  required?: boolean;
  options?: { value: string; label: string }[];
  placeholder?: string;
  hint?: string;
  colSpan?: 1 | 2;
  step?: string;
  min?: number;
  /** Hide this field based on the current form values (e.g. a conditional field). */
  hidden?: (values: Record<string, FormValue>) => boolean;
}

interface EntityFormModalProps {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  fields: FormFieldSchema[];
  initialValues: Record<string, FormValue>;
  onSubmit: (values: Record<string, FormValue>) => Promise<void>;
  submitLabel?: string;
  size?: "sm" | "md" | "lg" | "xl";
  /** Extra content rendered above the generated fields (e.g. a line-items editor). */
  extra?: React.ReactNode;
}

export function EntityFormModal({
  open,
  onClose,
  title,
  description,
  fields,
  initialValues,
  onSubmit,
  submitLabel = "حفظ البيانات",
  size = "md",
  extra,
}: EntityFormModalProps) {
  const [values, setValues] =
    useState<Record<string, FormValue>>(initialValues);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(false);
  const toast = useToast();

  // Re-seed local state whenever the modal is (re)opened with different initialValues.
  // Keyed remount from the parent (key={record?.id ?? "new"}) is the intended usage,
  // but this guard keeps behavior sane even without it.
  const [seeded, setSeeded] = useState(initialValues);
  if (open && seeded !== initialValues) {
    setSeeded(initialValues);
    setValues(initialValues);
  }

  function setField(name: string, value: FormValue) {
    setValues((prev) => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setSubmitting(true);
    try {
      await onSubmit(values);
      onClose();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.errors) setErrors(err.errors);
        toast.error(err.summary);
      } else {
        toast.error("حدث خطأ أثناء حفظ البيانات، يرجى إعادة المحاولة.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      description={description}
      size={size}
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            إلغاء
          </Button>
          <Button onClick={handleSubmit} loading={submitting}>
            {submitLabel}
          </Button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
        {extra}
        {fields
          .filter((f) => !f.hidden?.(values))
          .map((field) => {
            const value = values[field.name];
            const error = errors[field.name]?.[0];
            const span = field.colSpan === 1 ? "col-span-1" : "col-span-2";

            if (field.type === "checkbox") {
              return (
                <div key={field.name} className={`${span} pt-1`}>
                  <Checkbox
                    label={field.label}
                    checked={Boolean(value)}
                    onChange={(e) => setField(field.name, e.target.checked)}
                  />
                </div>
              );
            }

            if (field.type === "select") {
              return (
                <Select
                  key={field.name}
                  label={field.label}
                  required={field.required}
                  wrapClassName={span}
                  placeholder={field.placeholder ?? "اختر من القائمة..."}
                  value={(value as string) ?? ""}
                  error={error}
                  hint={field.hint}
                  onChange={(e) => setField(field.name, e.target.value)}
                >
                  {field.options?.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </Select>
              );
            }

            if (field.type === "textarea") {
              return (
                <Textarea
                  key={field.name}
                  label={field.label}
                  required={field.required}
                  wrapClassName={span}
                  placeholder={field.placeholder}
                  hint={field.hint}
                  error={error}
                  value={(value as string) ?? ""}
                  onChange={(e) => setField(field.name, e.target.value)}
                />
              );
            }

            return (
              <Input
                key={field.name}
                label={field.label}
                required={field.required}
                wrapClassName={span}
                type={
                  field.type === "number"
                    ? "number"
                    : field.type === "date"
                      ? "date"
                      : field.type === "email"
                        ? "email"
                        : "text"
                }
                step={field.step}
                min={field.min}
                placeholder={field.placeholder}
                hint={field.hint}
                error={error}
                value={(value as string | number) ?? ""}
                onChange={(e) =>
                  setField(
                    field.name,
                    field.type === "number" ? e.target.value : e.target.value,
                  )
                }
              />
            );
          })}
      </form>
    </Modal>
  );
}
