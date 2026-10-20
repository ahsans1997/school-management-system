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
    <section className="card mt-8 p-5">
      <h2 className="mb-3 text-sm font-semibold">Room timetable</h2>
      <form role="search" onSubmit={lookup} className="mb-3 flex gap-2">
        <input
          type="search"
          aria-label="Room"
          placeholder="Room, e.g. 101"
          maxLength={50}
          value={room}
          onChange={(e) => setRoom(e.target.value)}
          className="input input-sm"
        />
        <button type="submit" className="btn btn-secondary">
          Show
        </button>
      </form>
      {error && (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      )}
      {shown !== null && (
        <>
          <p className="mb-2 text-xs text-slate-500">Room {shown}</p>
          <RoutineGrid slots={slots} perspective="room" />
        </>
      )}
    </section>
  );
}
