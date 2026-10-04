"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ClassSubjectsManager } from "@/features/subjects/ClassSubjectsManager";

export default function ClassSubjectsPage({
  params,
}: {
  params: Promise<{ classId: string }>;
}) {
  const { classId } = use(params);

  return (
    <RequireAuth permission="subject.view">
      <ClassSubjectsManager classId={Number(classId)} />
    </RequireAuth>
  );
}
