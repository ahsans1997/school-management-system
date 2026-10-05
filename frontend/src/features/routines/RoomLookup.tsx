"use client";

import { useState, type FormEvent } from "react";
import { RoutineGrid } from "@/components/RoutineGrid";
import { errorMessage } from "@/lib/errors";
import * as routineService from "@/services/routineService";
import type { RoutineSlot } from "@/types/routine";

/** Who uses a room when — the room view of the same routine slots. */
export function RoomLookup() {
  const [room, setRoom] = useState("");
  const [shown, setShown] = useState<string | null>(null);
  const [slots, setSlots] = useState<RoutineSlot[]>([]);
  const [error, setError] = useState<string | null>(null);

  async function lookup(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = room.trim();
    if (!name) return;
    setError(null);
    try {
      setSlots((await routineService.getRoomRoutine(name)).slots);
      setShown(name);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <section className="mt-8 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Room timetable</h2>
      <form role="search" onSubmit={lookup} className="mb-3 flex gap-2">
        <input
          type="search"
          aria-label="Room"
          placeholder="Room, e.g. 101"
          maxLength={50}
          value={room}
          onChange={(e) => setRoom(e.target.value)}
          className="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <button
          type="submit"
          className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
        >
          Show
        </button>
      </form>
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
      {shown !== null && (
        <>
          <p className="mb-2 text-xs text-gray-500">Room {shown}</p>
          <RoutineGrid slots={slots} perspective="room" />
        </>
      )}
    </section>
  );
}
