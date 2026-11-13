"use client";

import { cn } from "@/lib/utils";
import { AlertCircle, CheckCircle2, FileText, ImageIcon, Loader2, UploadCloud, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useRef, useState, type DragEvent } from "react";

export const UPLOAD_ACCEPT = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
export const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
export const UPLOAD_MAX_FILES = 3;

export type UploadState = "idle" | "uploading" | "success" | "error";

const formatSize = (bytes: number) => (bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

/**
 * Dropzone (Figma FileUpload): JPG/PNG/WebP/PDF, max 5 MB each, max 3 files.
 * States: idle, dragging (local), uploading, success, error. Controlled `files`.
 */
export function FileUpload({
  files,
  onChange,
  state = "idle",
  error,
  disabled,
  label,
}: {
  files: File[];
  onChange: (files: File[]) => void;
  state?: UploadState;
  error?: string | null;
  disabled?: boolean;
  label?: string;
}) {
  const t = useTranslations("upload");
  const id = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);
  const [localErrors, setLocalErrors] = useState<string[]>([]);
  const busy = state === "uploading" || disabled;

  const add = (incoming: FileList | File[]) => {
    const errors: string[] = [];
    const next = [...files];
    for (const f of Array.from(incoming)) {
      if (!UPLOAD_ACCEPT.includes(f.type)) errors.push(t("errors.type", { name: f.name }));
      else if (f.size > UPLOAD_MAX_BYTES) errors.push(t("errors.size", { name: f.name }));
      else if (next.length >= UPLOAD_MAX_FILES) {
        errors.push(t("errors.count"));
        break;
      } else if (!next.some((x) => x.name === f.name && x.size === f.size)) next.push(f);
    }
    setLocalErrors(errors);
    onChange(next);
  };

  const onDrop = (e: DragEvent) => {
    e.preventDefault();
    setDragging(false);
    if (!busy && e.dataTransfer.files.length) add(e.dataTransfer.files);
  };

  const shownErrors = [...localErrors, ...(error ? [error] : [])];
  const visual = dragging ? "dragging" : state;

  return (
    <div className="flex flex-col gap-2">
      <span id={`${id}-label`} className="text-sm text-ink-700">
        {label ?? t("label")}
      </span>
      <div
        onDragEnter={(e) => {
          e.preventDefault();
          if (!busy) setDragging(true);
        }}
        onDragOver={(e) => e.preventDefault()}
        onDragLeave={(e) => {
          if (!e.currentTarget.contains(e.relatedTarget as Node)) setDragging(false);
        }}
        onDrop={onDrop}
        className={cn(
          "flex flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed px-4 py-8 text-center transition-colors",
          visual === "dragging" && "border-primary-500 bg-primary-100",
          visual === "idle" && "border-ink-300 bg-ink-100",
          visual === "uploading" && "border-primary-500 bg-white",
          visual === "success" && "border-success bg-success-100",
          visual === "error" && "border-error bg-error-100",
        )}
      >
        {visual === "uploading" ? (
          <Loader2 className="size-8 animate-spin text-primary-700" aria-hidden />
        ) : visual === "success" ? (
          <CheckCircle2 className="size-8 text-success" aria-hidden />
        ) : visual === "error" ? (
          <AlertCircle className="size-8 text-error" aria-hidden />
        ) : (
          <UploadCloud className="size-8 text-primary-700" aria-hidden />
        )}
        <p className="text-ink-900" aria-live="polite">
          {visual === "dragging" ? (
            t("dragging")
          ) : visual === "uploading" ? (
            t("uploading")
          ) : visual === "success" ? (
            t("success")
          ) : (
            <>
              {t("drop")}{" "}
              <button
                type="button"
                className="font-semibold text-primary-700 underline disabled:opacity-50"
                onClick={() => inputRef.current?.click()}
                disabled={busy || files.length >= UPLOAD_MAX_FILES}
                aria-describedby={`${id}-hint`}
              >
                {t("browse")}
              </button>
            </>
          )}
        </p>
        <p id={`${id}-hint`} className="text-xs text-ink-500">
          {t("hint")}
        </p>
        <input
          ref={inputRef}
          type="file"
          className="sr-only"
          tabIndex={-1}
          aria-labelledby={`${id}-label`}
          accept={UPLOAD_ACCEPT.join(",")}
          multiple
          disabled={busy}
          onChange={(e) => {
            if (e.target.files) add(e.target.files);
            e.target.value = "";
          }}
        />
      </div>
      {files.length > 0 && (
        <ul className="flex flex-col gap-2" aria-labelledby={`${id}-label`}>
          {files.map((f) => (
            <li key={f.name + f.size} className="flex items-center gap-3 rounded-control border border-ink-100 bg-white px-3 py-2 text-sm">
              {f.type === "application/pdf" ? <FileText className="size-5 shrink-0 text-primary-700" aria-hidden /> : <ImageIcon className="size-5 shrink-0 text-primary-700" aria-hidden />}
              <span className="min-w-0 flex-1 truncate text-ink-900">{f.name}</span>
              <span className="shrink-0 text-ink-500">{formatSize(f.size)}</span>
              {state === "success" ? (
                <CheckCircle2 className="size-5 text-success" aria-hidden />
              ) : (
                <button
                  type="button"
                  className="grid size-8 place-items-center rounded-full hover:bg-ink-100 disabled:opacity-50"
                  aria-label={t("remove", { name: f.name })}
                  disabled={busy}
                  onClick={() => onChange(files.filter((x) => x !== f))}
                >
                  <X className="size-4" aria-hidden />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
      {shownErrors.map((msg) => (
        <p key={msg} className="flex items-center gap-1 text-sm text-error" role="alert">
          <AlertCircle className="size-4 shrink-0" aria-hidden />
          {msg}
        </p>
      ))}
    </div>
  );
}
