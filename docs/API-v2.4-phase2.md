# API v2.4, Phase 2: contract changes for the frontend

Date: 25 Nov 2026. Scope: PLAN-v2.4 phase 2 (B2.1–B2.4) and what the website and the partner panel need for F1–F3.
Everything is additive: existing requests keep working and existing response fields are unchanged, except the
rooms listing (B2.4, see §4), which now returns unavailable rooms instead of hiding them.

General rules (unchanged): base URL `/api/v1`, `?locale=en|it` (also drives the validation messages below),
`?currency=`, `?nationality=` / `X-Nationality`, money as `{amount_eur, amount, currency}` on catalogue resources and
plain EUR numbers in quotes. Prices are always computed on the server. Errors: `422 {message, errors: {key: [messages]}}`.

Quote responses keep their shape: `{data: {valid, service_type, ...context, lines[], retail_total, total, deposit_percent,
deposit_amount, balance_amount, currency, converted, errors}}`, with `422 {message, errors, data}` when the quote breaks a
rule (the payload is still returned so the widget can show the price next to the error).

---

## 1. Multi-room accommodation (B2.1, B2.2)

### 1.1 `POST /rooms/quote`

Two accepted payloads.

**Single room (unchanged, backward compatible)** — now also accepts `extra_beds` and `cots`:

```json
{ "room_id": 12, "check_in": "2026-12-01", "check_out": "2026-12-04", "adults": 2, "children": 1, "child_ages": [6], "extra_beds": 1, "cots": 0 }
```

**Several rooms of one building** — every guest is allocated to exactly one room:

```json
{
  "check_in": "2026-12-01",
  "check_out": "2026-12-03",
  "adults": 3,
  "child_ages": [9],
  "rooms": [
    { "room_id": 12, "adults": 2, "children_ages": [], "extra_beds": 0, "cots": 0 },
    { "room_id": 14, "adults": 1, "children_ages": [9], "extra_beds": 0, "cots": 1 }
  ]
}
```

- `rooms` 1–10 items; `room_id` must be distinct; `adults` 0–20 (0 is accepted by validation so the engine can return
  the allocation message); `children_ages[]` 0–17; `extra_beds` / `cots` 0–10 (limited per room by the room settings).
- `adults` + `child_ages` at the top level are **optional** with `rooms`: when sent, the engine checks that the
  allocation covers exactly that party ("Every guest must be in a room" / "These rooms cannot fit N guests, add a room").
  Send them from the RoomAllocationBoard so unallocated guests are reported.
- Partners: `POST /partner/quotes` with `product_type: "room"` accepts the same `rooms[]` (no `id` needed). No commission
  on accommodation.

**Response (200)** — new context fields `building_id`, `child_ages` and `rooms[]`; new line types `extra_bed`, `cot`:

```json
{
  "data": {
    "valid": true,
    "service_type": "accommodation",
    "room_id": 12,
    "building_id": 3,
    "pricing_mode": "per_room",
    "check_in": "2026-12-01",
    "check_out": "2026-12-03",
    "nights": 2,
    "adults": 3,
    "children": 1,
    "child_ages": [9],
    "rooms": [
      { "index": 0, "room_id": 12, "name": "Double", "number": "102", "room_type": "double", "pricing_mode": "per_room",
        "adults": 2, "children_ages": [], "extra_beds": 0, "cots": 0, "nights": 2,
        "room_total": 160, "extras_total": 0, "total": 160, "guests": [] },
      { "index": 1, "room_id": 14, "name": "Family", "number": "201", "room_type": "family", "pricing_mode": "per_person",
        "adults": 1, "children_ages": [9], "extra_beds": 0, "cots": 1, "nights": 2,
        "room_total": 225, "extras_total": 20, "total": 245,
        "guests": [
          { "type": "adult", "age": null, "total": 150, "discount": 0 },
          { "type": "child", "age": 9, "total": 75, "discount": 75 }
        ] }
    ],
    "tax_included": true,
    "tax_per_night": null,
    "season_name": "High season",
    "lines": [
      { "type": "night", "label": "Room 1 · Double · Tue 1 Dec 2026", "quantity": 1, "unit_price": 80, "total": 80,
        "meta": { "room_id": 12, "room_index": 0, "date": "2026-12-01", "season": null, "price_per_night": 80, "price_source": "product_base" } },
      { "type": "cot", "label": "Room 2 · Family · Cot", "quantity": 2, "unit_price": 10, "total": 20,
        "meta": { "room_id": 14, "room_index": 1, "cots": 1, "nights": 2 } }
    ],
    "retail_total": 405,
    "total": 405,
    "deposit_percent": 30,
    "deposit_amount": 121.5,
    "balance_amount": 283.5,
    "currency": "EUR",
    "converted": { "total": 405, "deposit": 121.5, "balance": 283.5 },
    "errors": {}
  }
}
```

Notes:
- `rooms[].guests[]` is only filled for **per-person** rooms (SRS 7.4: each person's price and discount). Per-room rooms
  have one price for any occupancy within capacity, so `guests` is empty.
- Single-room requests keep the old line labels (`"Tue 1 Dec 2026 · High season"`); multi-room labels start with
  `"Room N · <room name> · "`. Use `meta.room_index` to group lines per room, never the label.
- Extra beds and cots are charged **per night and per bed/cot**: `quantity = beds × nights`.
- **Each extra bed adds one place** to the room's `max_total` (not to `max_adults`/`max_children`). Cots do not change
  the capacity.
- Taxes (buildings with Taxes included off) are one `tax` line: `quantity = nights × rooms`.

### 1.2 `POST /bookings` (accommodation)

Same `rooms[]` + `check_in` / `check_out` (top-level `room_id`, `adults`... still work for one room). The service is
inferred from `rooms[].room_id` (or send `"service": "accommodation"`). `travelers[]` must contain the whole party
(adults + children with `age`); travellers are assigned to rooms automatically (adults in order, children matched by age).

```json
{
  "check_in": "2026-12-01", "check_out": "2026-12-03",
  "rooms": [
    { "room_id": 12, "adults": 2 },
    { "room_id": 14, "adults": 1, "children_ages": [9], "cots": 1 }
  ],
  "nationality_code": "IT",
  "lead": { "name": "Anna Rossi", "email": "anna@example.com", "phone": "+39 333 000000" },
  "travelers": [
    { "first_name": "Anna", "last_name": "Rossi", "type": "adult" },
    { "first_name": "Marco", "last_name": "Rossi", "type": "adult" },
    { "first_name": "Luca", "last_name": "Rossi", "type": "adult" },
    { "first_name": "Sara", "last_name": "Rossi", "type": "child", "age": 9 }
  ]
}
```

201 `BookingResource`; every room's nights are **held** until `payment_due_at`. `BookingResource` changes (all bookings):

```json
{
  "end_date": "2026-12-03",
  "rooms": [
    { "room_id": 14, "room_option_id": null, "stage_position": null, "name": "Family", "number": "201",
      "pricing_method": "per_person", "adults": 1, "children_ages": [9], "extra_beds": 0, "cots": 1,
      "nights": 2, "price": 112.5, "extras_total": 20, "total": 245, "traveler_ids": [31, 34] }
  ]
}
```

`end_date` is the last service day (check-out for stays, last day of multi-day trips and packages); `traveler_ids`
is filled when the booking is returned right after creation (empty array otherwise).

### 1.3 `GET /rooms/combinations` — best combinations (new)

Query: `building` (slug, required), `check_in`, `check_out` (required, Y-m-d, check-in ≥ today), `adults` (1–20),
`child_ages[]` (0–17), `limit` (1–20, default 10). Throttle 60/min.

Returns the cheapest sets of **free** rooms of the building that hold the whole party under SRS 7.2 (each room ≥ 1 adult,
capacity per room, no empty room), up to 5 rooms per combination, sorted by total price, then number of rooms, then
number of extra beds. Identical rooms are grouped so the list is not flooded with equivalent combinations.
`request` is ready to send to `/rooms/quote` or `/bookings`.

**Extra beds and cots (v2.4 phase 2 completion)** — additive fields:
- The suggestions use **extra beds** within each room's `max_extra_beds` when they make a combination possible or
  cheaper than another room: one bed per guest above the room's `max_total` (beds never raise `max_adults` /
  `max_children`). They are priced exactly as in `/rooms/quote` (`extra_bed_price` × beds × nights) and included in
  `total`.
- Each `rooms[]` item has `extra_beds` and `cots`, and `capacity` gains `max_extra_beds` and `max_cots`; each
  combination has `extra_beds` (sum of the rooms). `request.rooms[]` carries the same `extra_beds` / `cots`.
- **Cots are never suggested** (`cots` is always `0`): a cot adds no place and rooms have no infant rule that requires
  one. The guest can still add cots (up to `capacity.max_cots`) before sending the request to `/rooms/quote`.

```json
{
  "data": [
    {
      "rooms_count": 2,
      "capacity": 4,
      "extra_beds": 0,
      "total_eur": 300,
      "total": { "amount_eur": 300, "amount": 300, "currency": "EUR" },
      "tax_included": true,
      "rooms": [
        { "room_id": 11, "name": "Single", "number": "101", "room_type": "single", "pricing_mode": "per_room",
          "capacity": { "max_adults": 1, "max_children": 0, "max_total": 1, "max_extra_beds": 0, "max_cots": 0 },
          "adults": 1, "children_ages": [], "extra_beds": 0, "cots": 0,
          "total": { "amount_eur": 100, "amount": 100, "currency": "EUR" } },
        { "room_id": 14, "name": "Triple", "number": "301", "room_type": "triple", "pricing_mode": "per_room",
          "capacity": { "max_adults": 3, "max_children": 2, "max_total": 3, "max_extra_beds": 1, "max_cots": 1 },
          "adults": 2, "children_ages": [9], "extra_beds": 0, "cots": 0,
          "total": { "amount_eur": 200, "amount": 200, "currency": "EUR" } }
      ],
      "request": {
        "check_in": "2026-12-01", "check_out": "2026-12-03",
        "rooms": [
          { "room_id": 11, "adults": 1, "children_ages": [], "extra_beds": 0, "cots": 0 },
          { "room_id": 14, "adults": 2, "children_ages": [9], "extra_beds": 0, "cots": 0 }
        ]
      }
    }
  ],
  "meta": {
    "building": { "id": 3, "slug": "hotel-nile-view", "name": "Hotel Nile View" },
    "check_in": "2026-12-01", "check_out": "2026-12-03", "nights": 2,
    "adults": 3, "child_ages": [9], "count": 1, "message": null
  }
}
```

No combination: `data: []` and `meta.message` = "These rooms cannot fit N guests, add a room." (localized). Unknown
building: 404.

### 1.4 Room cards and room detail: extra beds and cots

`GET /rooms`, `/rooms/{id}`, building rooms: new `extra_beds` and `cots` on each card:

```json
"extra_beds": { "max": 1, "price": { "amount_eur": 25, "amount": 25, "currency": "EUR" } },
"cots": { "max": 0, "price": null }
```

`price` is per night; `null` when the room offers none.

---

## 2. Optional rooms in multi-day trips, Nile trips and packages (B2.3)

### 2.1 Product detail: `room_options[]`

`GET /trips/{slug}` (multi-day and Nile trips with at least one night; always `[]` for single-day trips) and
`GET /packages/{slug}` (per stage):

```json
"room_options": [
  {
    "id": 7,
    "stage_position": null,
    "unit_type": "cabin",
    "unit_type_label": "Cabin",
    "name": "Upper deck cabin upgrade",
    "stars": 5,
    "pricing_mode": "per_room",
    "price_period": "per_stay",
    "price": { "amount_eur": 120, "amount": 120, "currency": "EUR" },
    "nights": 4,
    "capacity": { "max_adults": 2, "max_children": 1, "max_total": 3 },
    "max_quantity": 10
  }
]
```

- `unit_type`: `room | suite | cabin | villa | tent`. `pricing_mode`: `per_room | per_person`. `price_period`:
  `per_night | per_stay`.
- `price` is the base price of the visitor's nationality group; the quote applies the season of the stay's first night
  (trip travel date, or the first day of the package stage).
- Packages: `stage_position` (1-based) is the stage the room belongs to and `nights` that stage's nights. Rooms of a
  stage only cover that stage.
- No availability calendar: these rooms are priced add-ons (SRS 7.1).

### 2.2 Quotes and bookings: `room_options[]`

`POST /trips/{slug}/quote`, `POST /packages/{slug}/quote`, `POST /bookings` (trip/package), `POST /partner/quotes` and
`POST /partner/bookings` (`product_type` trip/package) accept:

```json
"room_options": [
  { "room_option_id": 7, "adults": 2, "children_ages": [8] },
  { "room_option_id": 7, "adults": 1, "children_ages": [] }
]
```

- Optional. One item per room (the same option can be repeated up to `max_quantity`).
- When rooms are sent, **every guest must be in exactly one room** (for packages: in each stage that has rooms).
- Price: per room = `price × nights` (per night) or `price` (per stay), whatever the occupancy within capacity; per
  person = adults × price + each child by the product's age bands (`children policy`), × nights when per night.
- Partner quotes/bookings: the product's commission applies to the room lines like to the rest of the trip.

New quote line type `room`:

```json
{ "type": "room", "label": "Double room ★★★★ (2 ad. + 1 ch.)", "quantity": 1, "unit_price": 120, "total": 120,
  "meta": { "room_option_id": 7, "stage_position": null, "unit_type": "room", "stars": 4, "pricing_mode": "per_room",
            "price_period": "per_night", "price": 60, "nights": 2, "start_date": "2026-11-25", "adults": 2,
            "children_ages": [8], "guests": [], "price_source": "product_base" } }
```

Per-person rooms fill `meta.guests[]` with the children: `{type: "child", age, price, discount}` (whole stay).

Bookings store each room in `rooms[]` of the `BookingResource` with `room_option_id`, `stage_position`, `adults`,
`children_ages`, `total`.

---

## 3. Validation keys and messages (EN / IT)

The engine messages follow `?locale=`. `:room` is "Room 1", "Room 2"... (position in `rooms[]` / `room_options[]`);
for single-room requests it is "This room" / "Questa camera" ("Room" / "La camera" in the capacity message).

| Key | EN | IT |
| --- | --- | --- |
| `rooms` | Every guest must be in a room. | Ogni ospite deve essere assegnato a una camera. |
| `rooms` | These rooms cannot fit 5 guests, add a room. | Queste camere non possono ospitare 5 persone, aggiungi una camera. |
| `rooms` | All rooms of a booking must be in the same building. | Tutte le camere di una prenotazione devono essere nella stessa struttura. |
| `rooms` | Choose at most 10 rooms. | Scegli al massimo 10 camere. |
| `rooms.N.room_id` | Room 2 is selected twice: rooms cannot be shared. | Camera 2 è selezionata due volte: le camere non possono essere condivise. |
| `rooms.N.room_id` (also `room_id`) | This room is not available. | Questa camera non è disponibile. |
| `rooms.N.adults` (`adults` single room) | Room 1 exceeds its capacity of 3. | Camera 1 supera la sua capacità di 3. |
| `rooms.N.adults` (`adults`) | Room 1 sleeps at most 2 adults. | Camera 1 ospita al massimo 2 adulti. |
| `rooms.N.adults` (`adults`) | A child cannot stay in a room without an adult. | Un bambino non può stare in una camera senza un adulto. |
| `rooms.N.adults` | Room 2 is empty: every booked room needs at least one guest. | Camera 2 è vuota: ogni camera prenotata deve avere almeno un ospite. |
| `rooms.N.children_ages` (`children`) | Room 1 accepts at most 1 children. | Camera 1 accetta al massimo 1 bambini. |
| `rooms.N.children_ages` (`children`) | Room 1 does not accept children. | Camera 1 non accetta bambini. |
| `rooms.N.children_ages` (`children`) | Room 1 accepts children from 6 years. | Camera 1 accetta bambini da 6 anni. |
| `rooms.N.extra_beds` (`extra_beds`) | Room 1 allows at most 1 extra beds. | Camera 1 consente al massimo 1 letti aggiuntivi. |
| `rooms.N.cots` (`cots`) | Room 1 allows at most 1 cots. | Camera 1 consente al massimo 1 culle. |
| `check_in` | Room 2 is not available on 2 Dec 2026. (single: "This room is not available on …") | Camera 2 non è disponibile il 2 Dec 2026. |
| `check_in` | Check-in cannot be in the past. | Il check-in non può essere nel passato. |
| `check_out` | Check-out must be after check-in. | Il check-out deve essere successivo al check-in. |
| `check_out` | Room 1 requires a minimum stay of 3 nights. | Camera 1 richiede un soggiorno minimo di 3 notti. |
| `check_out` | Room 1 allows a maximum stay of 14 nights. | Camera 1 consente un soggiorno massimo di 14 notti. |
| `child_ages` | Enter the age of every child. | Inserisci l'età di ogni bambino. |
| `room_options` | Every guest must be in a room. | Ogni ospite deve essere assegnato a una camera. |
| `room_options` | These rooms cannot fit 4 guests, add a room. | Queste camere non possono ospitare 4 persone, aggiungi una camera. |
| `room_options` | Stage 1: every guest must be in a room. | Tappa 1: ogni ospite deve essere assegnato a una camera. |
| `room_options` | Stage 1: these rooms cannot fit 3 guests, add a room. | Tappa 1: queste camere non possono ospitare 3 persone, aggiungi una camera. |
| `room_options` | Rooms can only be added to multi-day products. | Le camere si possono aggiungere solo ai prodotti di più giorni. |
| `room_options.N.room_option_id` | Room option #7 is not available for this trip. | L'opzione camera #7 non è disponibile per questo trip. |
| `room_options.N.room_option_id` | You can add at most 2 × Royal suite ★★★★★. | Puoi aggiungere al massimo 2 × Royal suite ★★★★★. |
| `room_options.N.adults` / `.children_ages` | same capacity / child / empty-room messages as `rooms.N.*` | |

Dates in `not_available_on` use the English short format (`j M Y`). The single-room messages are unchanged from v2.3
(existing widgets keep working). Laravel validation messages (`required`, `integer`, `distinct`...) stay in English.

---

## 4. Rooms listing: unavailable rooms and group by building (B2.4)

`GET /rooms` — **behaviour change**: with `check_in` + `check_out` and/or `guests`, rooms that cannot be booked are
**returned** (after the bookable ones) instead of hidden. Every card gets:

```json
"available": false,
"unavailable_reason": "booked",
"unavailable_reasons": ["booked", "capacity"]
```

| Reason | When |
| --- | --- |
| `booked` | a night of the stay is booked or held (unexpired hold) by another booking |
| `closed` | a night is closed (maintenance, owner use, imported external calendar) |
| `min_stay` | the stay is shorter than `min_nights` or longer than `max_nights` |
| `capacity` | `guests` is more than `max_total` + `max_extra_beds` |

`unavailable_reason` is the first (most important) reason, `null` when available. Without dates and guests every room is
`available: true`. The old behaviour is still there with `only_available=1`.

New query parameters:

- `only_available=1`: hide unavailable rooms (v2.3 behaviour).
- `group_by=building`: same pagination, but `data` is grouped by building (rooms of the page, ordered by building):

```json
{
  "data": [
    { "building": { "...BuildingCard": "..." }, "available_count": 2, "rooms": [ { "...RoomCard": "..." } ] }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "per_page": 12, "total": 27 }
}
```

---

## 5. Password links: welcome e-mail and forgot password (new)

Customer accounts created by staff (admin booking for a new customer) receive a **"Welcome, set your password"**
e-mail (event `customer_welcome`, editable in Admin → Notifications, logged in the notification log). "Forgot
password" sends the `password_reset` e-mail. Both buttons open a **website page the frontend must provide**:

```
{FRONTEND_URL}/{locale}/reset-password?token=<token>&email=<url-encoded e-mail>
```

`locale` is the account language (`en` / `it`). The page asks for the new password and posts it:

**`POST /auth/reset-password`** (throttle 10/min)

```json
{ "email": "anna@example.com", "token": "<token from the link>", "password": "new-password", "password_confirmation": "new-password" }
```

- 200 `{ "message": "Your password has been saved. You can now sign in." }` — then send the user to sign-in (no token
  is returned; every existing API token of the account is revoked).
- 422 `errors.token`: "This password link is invalid or has expired. Ask for a new one with "Forgot password"."
  Validation errors on `email` / `password` (Laravel default password rules, `confirmed`).
- Welcome links are valid **7 days** (`AUTH_WELCOME_TOKEN_EXPIRE`, minutes), reset links **60 minutes**; a link works once.

**`POST /auth/forgot-password`** (throttle 5/min) `{ "email": "anna@example.com" }` → always 200
`{ "message": "If an account exists for this e-mail, we have sent a link to choose a new password." }` (also for unknown
or staff e-mails; at most one e-mail per account per minute). Messages follow `?locale=`.

---

## 6. Not for the website (admin only, for reference)

Admin JSON endpoints used by the back office (session auth, CSRF): `POST /admin/bookings/quote`, `POST /admin/bookings`,
`POST /admin/bookings/{id}/preview`, `PUT /admin/bookings/{id}` (trip/package bookings also accept `adults`, `child_ages[]`,
`units[]` and `addons[]`), `POST /admin/calendar/move`, `POST /admin/bookings/{id}/release-hold`.
iCal export per room: `GET /ical/rooms/{room}/{token}.ics` (public secret URL shown on the room page, for channel managers).
