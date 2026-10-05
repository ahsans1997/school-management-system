"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { TeacherProfile } from "@/features/teachers/TeacherProfile";

export default function TeacherPage({
  params,
}: {
  params: Promise<{ teacherId: string }>;
}) {
  const { teacherId } = use(params);

  return (
    <RequireAuth permission="teacher.view">
      <TeacherProfile teacherId={Number(teacherId)} />
    </RequireAuth>
  );
}
