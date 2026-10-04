"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { SectionsManager } from "@/features/sections/SectionsManager";

export default function SectionsPage({
  params,
}: {
  params: Promise<{ classId: string }>;
}) {
  const { classId } = use(params);

  return (
    <RequireAuth permission="section.view">
      <SectionsManager classId={Number(classId)} />
    </RequireAuth>
  );
}
