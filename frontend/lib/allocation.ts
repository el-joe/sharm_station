/**
 * Room allocation rules shared by the RoomAllocationBoard (multi-room stays, B2.1) and the optional trip rooms
 * (B2.3). They mirror the server's SRS 7.2 checks so the visitor sees the problem before the quote comes back;
 * the server stays the source of truth and its 422 messages are shown as well.
 */

export interface Capacity {
  max_adults: number;
  max_children: number;
  max_total: number;
}

export interface Traveller {
  /** "a0", "a1"… adults; "c0", "c1"… children (index in the party's child_ages). */
  id: string;
  type: "adult" | "child";
  /** Position among the adults / children (0-based). */
  n: number;
  age: number | null;
}

export interface AllocRoom {
  /** Stable key (room id for stays, a generated key for optional rooms). */
  key: string;
  capacity: Capacity;
  /** Extra beds add one place each to max_total (not to max_adults / max_children). */
  extra_beds?: number;
  /** Youngest child age the room accepts (null = any). */
  min_child_age?: number | null;
}

export type AllocationErrorKey = "unassigned" | "cannotFit" | "empty" | "childAlone" | "maxAdults" | "noChildren" | "maxChildren" | "capacity";

export interface AllocationError {
  key: AllocationErrorKey;
  /** 0-based position of the room the message is about (null = whole allocation). */
  room: number | null;
  values: Record<string, number>;
}

export function partyTravellers(adults: number, childAges: (number | null)[]): Traveller[] {
  return [
    ...Array.from({ length: adults }, (_, n) => ({ id: `a${n}`, type: "adult" as const, n, age: null })),
    ...childAges.map((age, n) => ({ id: `c${n}`, type: "child" as const, n, age })),
  ];
}

export const roomCapacity = (r: AllocRoom) => r.capacity.max_total + (r.extra_beds ?? 0);

/** Who is in each room (by room key). */
export function occupants(travellers: Traveller[], assignment: Record<string, string | null | undefined>, roomKey: string) {
  const list = travellers.filter((t) => assignment[t.id] === roomKey);
  return { list, adults: list.filter((t) => t.type === "adult").length, children: list.filter((t) => t.type === "child") };
}

/** SRS 7.2: every guest in exactly one room, capacity incl. extra beds, at least one adult with children, no empty room. */
export function validateAllocation(rooms: AllocRoom[], travellers: Traveller[], assignment: Record<string, string | null | undefined>): AllocationError[] {
  const errors: AllocationError[] = [];
  const keys = new Set(rooms.map((r) => r.key));
  const unassigned = travellers.filter((t) => !assignment[t.id] || !keys.has(assignment[t.id]!));
  const space = rooms.reduce((s, r) => s + roomCapacity(r), 0);
  if (rooms.length && space < travellers.length) errors.push({ key: "cannotFit", room: null, values: { count: travellers.length } });
  else if (unassigned.length) errors.push({ key: "unassigned", room: null, values: { count: unassigned.length } });

  rooms.forEach((r, i) => {
    const { adults, children } = occupants(travellers, assignment, r.key);
    const n = i + 1;
    if (adults + children.length === 0) {
      errors.push({ key: "empty", room: i, values: { n } });
      return;
    }
    if (adults === 0 && children.length > 0) errors.push({ key: "childAlone", room: i, values: { n } });
    if (adults > r.capacity.max_adults) errors.push({ key: "maxAdults", room: i, values: { n, max: r.capacity.max_adults } });
    if (children.length > 0 && r.capacity.max_children === 0) errors.push({ key: "noChildren", room: i, values: { n } });
    else if (children.length > r.capacity.max_children) errors.push({ key: "maxChildren", room: i, values: { n, max: r.capacity.max_children } });
    if (adults + children.length > roomCapacity(r)) errors.push({ key: "capacity", room: i, values: { n, max: roomCapacity(r) } });
  });
  return errors;
}

/**
 * Auto-assign: one adult per room first (no child-only room), then children next to an adult where they fit,
 * then the remaining adults. Guests that fit nowhere stay unassigned (the board reports them).
 */
export function autoAssign(rooms: AllocRoom[], travellers: Traveller[]): Record<string, string | null> {
  const out: Record<string, string | null> = Object.fromEntries(travellers.map((t) => [t.id, null]));
  const load = rooms.map(() => ({ adults: 0, children: 0 }));
  const fits = (i: number, type: "adult" | "child") => {
    const r = rooms[i];
    const l = load[i];
    if (l.adults + l.children >= roomCapacity(r)) return false;
    return type === "adult" ? l.adults < r.capacity.max_adults : l.children < r.capacity.max_children && l.adults > 0;
  };
  const put = (t: Traveller, i: number) => {
    out[t.id] = rooms[i].key;
    if (t.type === "adult") load[i].adults++;
    else load[i].children++;
  };
  const adults = travellers.filter((t) => t.type === "adult");
  const children = travellers.filter((t) => t.type === "child");
  const queue = [...adults];
  rooms.forEach((_, i) => {
    const a = queue[0];
    if (a && fits(i, "adult")) put(queue.shift()!, i);
  });
  for (const c of children) {
    const i = rooms.findIndex((_, j) => fits(j, "child"));
    if (i >= 0) put(c, i);
  }
  for (const a of queue) {
    const i = rooms.findIndex((_, j) => fits(j, "adult"));
    if (i >= 0) put(a, i);
  }
  return out;
}

/** A room placed in a stay (multi-room accommodation): which room and its extra beds / cots. */
export interface PickedRoom {
  room_id: number;
  extra_beds: number;
  cots: number;
}

/** Occupancy of one room as the API sends / returns it (`rooms[]` of quotes, bookings and combinations). */
export interface RoomOccupancy {
  room_id: number;
  adults: number;
  children_ages: number[];
}

/**
 * Who sleeps where from a list of room occupancies: adults in order, children matched by age (the same rule the
 * booking uses). Used to apply a "best combination" and to rebuild the board from a saved request.
 */
export function assignFromRooms(rooms: RoomOccupancy[], travellers: Traveller[]): Record<string, string | null> {
  const out: Record<string, string | null> = Object.fromEntries(travellers.map((tr) => [tr.id, null]));
  const freeAdults = travellers.filter((tr) => tr.type === "adult");
  const freeChildren = travellers.filter((tr) => tr.type === "child");
  for (const r of rooms) {
    for (let k = 0; k < r.adults; k++) {
      const a = freeAdults.shift();
      if (a) out[a.id] = String(r.room_id);
    }
    for (const age of r.children_ages) {
      const j = freeChildren.findIndex((ch) => ch.age === age);
      if (j >= 0) out[freeChildren.splice(j, 1)[0].id] = String(r.room_id);
    }
  }
  return out;
}

type Extras = { extra_beds?: number | null; cots?: number | null };

/**
 * Rooms of a GET /rooms/combinations result with their extra beds / cots. Newer API versions send them per room
 * (on `rooms[]` and / or `request.rooms[]`); older ones send none or 0, which means none.
 */
export function combinationPicks(c: { rooms: (Extras & { room_id: number })[]; request: { rooms: (Extras & RoomOccupancy)[] } }): PickedRoom[] {
  return c.request.rooms.map((r) => {
    const shown = c.rooms.find((x) => x.room_id === r.room_id);
    const count = (v: number | null | undefined) => Math.max(0, Math.trunc(Number(v ?? 0)) || 0);
    // Either place may carry them (older payloads have 0 in request.rooms): take what is set.
    return { room_id: r.room_id, extra_beds: Math.max(count(r.extra_beds), count(shown?.extra_beds)), cots: Math.max(count(r.cots), count(shown?.cots)) };
  });
}
