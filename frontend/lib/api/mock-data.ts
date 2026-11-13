/**
 * Demo fixtures mirroring what the Laravel seeders create (docs/ARCHITECTURE.md §8, phase 1).
 * Used when NEXT_PUBLIC_USE_MOCKS=true or when the API cannot be reached.
 * Text is bilingual ({ en, it }) and resolved per request locale by mock-api.ts.
 */
import type {
  AddonPricingBasis,
  BookingType,
  ChildAgeBand,
  Currency,
  GoogleReview,
  Locale,
  SeasonPrice,
  TripBadge,
  TripType,
} from "./types";

export type L = { en: string; it: string };
const l = (en: string, it: string): L => ({ en, it });

export const pick = (value: L, locale: Locale) => value[locale] ?? value.en;

export const MOCK_CURRENCIES: Currency[] = [
  { code: "EUR", symbol: "€", rate_to_eur: 1, is_enabled: true },
  { code: "USD", symbol: "$", rate_to_eur: 1.08, is_enabled: true },
  { code: "GBP", symbol: "£", rate_to_eur: 0.85, is_enabled: true },
  { code: "EGP", symbol: "E£", rate_to_eur: 52.4, is_enabled: true },
];

export interface MockDestination {
  id: number;
  slug: string;
  name: L;
  description: L;
  image: string;
  trips_count: number;
}

export const MOCK_DESTINATIONS: MockDestination[] = [
  { id: 1, slug: "cairo", name: l("Cairo", "Il Cairo"), description: l("Pyramids, the Sphinx and the Egyptian Museum.", "Piramidi, Sfinge e Museo Egizio."), image: "/images/cairo.webp", trips_count: 24 },
  { id: 2, slug: "luxor", name: l("Luxor", "Luxor"), description: l("Karnak, the Valley of the Kings and the West Bank.", "Karnak, la Valle dei Re e la riva occidentale."), image: "/images/luxor.webp", trips_count: 18 },
  { id: 3, slug: "aswan", name: l("Aswan", "Assuan"), description: l("Feluccas, Philae and Nubian villages.", "Feluche, Philae e villaggi nubiani."), image: "/images/aswan.webp", trips_count: 15 },
  { id: 4, slug: "sharm-el-sheikh", name: l("Sharm El Sheikh", "Sharm El Sheikh"), description: l("Coral reefs, desert safaris and Ras Mohammed.", "Barriere coralline, safari nel deserto e Ras Mohammed."), image: "/images/sharm.webp", trips_count: 21 },
  { id: 5, slug: "hurghada", name: l("Hurghada", "Hurghada"), description: l("Islands, snorkelling and Red Sea beaches.", "Isole, snorkeling e spiagge del Mar Rosso."), image: "/images/hurghada.webp", trips_count: 17 },
  { id: 6, slug: "alexandria", name: l("Alexandria", "Alessandria"), description: l("The Mediterranean pearl of Egypt.", "La perla mediterranea d'Egitto."), image: "/images/alexandria.webp", trips_count: 9 },
];

/** Standard seasons (recurring yearly). The factor scales each product's base prices. */
const SEASONS: { id: number; name: L; starts_on: string; ends_on: string; factor: number }[] = [
  { id: 1, name: l("Low season", "Bassa stagione"), starts_on: "2026-01-07", ends_on: "2026-03-31", factor: 0.72 },
  { id: 2, name: l("Spring", "Primavera"), starts_on: "2026-04-01", ends_on: "2026-06-30", factor: 0.86 },
  { id: 3, name: l("Summer", "Estate"), starts_on: "2026-07-01", ends_on: "2026-10-31", factor: 0.8 },
  { id: 4, name: l("High season", "Alta stagione"), starts_on: "2026-11-01", ends_on: "2026-12-19", factor: 1 },
  { id: 5, name: l("Christmas & New Year", "Natale e Capodanno"), starts_on: "2026-12-20", ends_on: "2027-01-06", factor: 1.28 },
];

const r = (n: number) => Math.round(n);

export interface MockSeason extends Omit<SeasonPrice, "name"> {
  name: L;
}

interface SeasonBase {
  adult?: number;
  child?: number;
  units?: Record<string, number>;
  tiers?: Record<string, number>;
  night?: number;
  /** Explicit headline prices per season id (overrides factor). */
  explicit?: Record<number, number>;
}

function seasons(base: SeasonBase, idOffset: number): MockSeason[] {
  return SEASONS.map((s) => {
    const f = s.factor;
    const headline = base.explicit?.[s.id] ?? r((base.adult ?? base.night ?? Object.values(base.units ?? {})[0] ?? 0) * f);
    const ratio = base.explicit && base.adult ? headline / base.adult : f;
    return {
      id: idOffset + s.id,
      name: s.name,
      starts_on: s.starts_on,
      ends_on: s.ends_on,
      recurring_yearly: true,
      is_active: true,
      price: headline,
      adult_price: base.adult != null ? headline : null,
      child_price: base.child != null ? r(base.child * ratio) : null,
      unit_prices: base.units ? Object.fromEntries(Object.entries(base.units).map(([k, v]) => [k, r(v * f)])) : null,
      tier_prices: base.tiers ? Object.fromEntries(Object.entries(base.tiers).map(([k, v]) => [k, r(v * ratio)])) : null,
      price_per_night: base.night != null ? headline : null,
    };
  });
}

export interface MockAddon {
  id: number;
  name: L;
  description: L;
  image?: string;
  price: number;
  pricing_basis: AddonPricingBasis;
  required: boolean;
  max_quantity: number;
  booking_types: BookingType[];
}

const ADDONS: Record<string, MockAddon> = {
  guide: { id: 1, name: l("Private guide", "Guida privata"), description: l("A dedicated Egyptologist for your party", "Un egittologo dedicato al vostro gruppo"), image: "/images/cairo.webp", price: 25, pricing_basis: "per_booking", required: false, max_quantity: 1, booking_types: ["private", "group"] },
  lunch: { id: 2, name: l("Lunch upgrade", "Pranzo superiore"), description: l("Three-course lunch by the Nile", "Pranzo di tre portate sul Nilo"), image: "/images/aswan.webp", price: 12, pricing_basis: "per_person", required: false, max_quantity: 1, booking_types: ["private", "group"] },
  balloon: { id: 3, name: l("Hot-air balloon", "Mongolfiera"), description: l("Sunrise flight over the Luxor West Bank", "Volo all'alba sulla riva occidentale di Luxor"), image: "/images/balloons.webp", price: 75, pricing_basis: "per_person", required: false, max_quantity: 1, booking_types: ["private", "group"] },
  photos: { id: 4, name: l("Underwater photos", "Foto subacquee"), description: l("A photographer joins you in the water", "Un fotografo vi accompagna in acqua"), image: "/images/sharm.webp", price: 30, pricing_basis: "per_booking", required: false, max_quantity: 1, booking_types: ["private", "group"] },
  fins: { id: 5, name: l("Fins and mask rental", "Noleggio maschera e pinne"), description: l("Quality snorkelling gear, sized on board", "Attrezzatura di qualità, taglie a bordo"), image: "/images/hurghada.webp", price: 5, pricing_basis: "per_person", required: false, max_quantity: 1, booking_types: ["private", "group"] },
  goggles: { id: 6, name: l("Helmet and goggles", "Casco e occhiali"), description: l("Per buggy, for dusty desert tracks", "Per buggy, per le piste polverose"), image: "/images/sharm.webp", price: 4, pricing_basis: "per_unit", required: false, max_quantity: 6, booking_types: ["private", "group"] },
  bedouinDinner: { id: 7, name: l("Bedouin dinner and show", "Cena beduina e spettacolo"), description: l("Barbecue under the stars with live music", "Barbecue sotto le stelle con musica dal vivo"), image: "/images/sharm.webp", price: 15, pricing_basis: "per_person", required: false, max_quantity: 1, booking_types: ["private", "group"] },
};

const CHILD_BANDS: ChildAgeBand[] = [
  { from: 0, to: 3, pays: "free" },
  { from: 4, to: 11, pays: "child" },
  { from: 12, to: 17, pays: "adult" },
];

const STANDARD_POLICIES = {
  cancellation: l(
    "Free cancellation up to 24 hours before departure for a full refund. Pay 30% today to reserve; the balance is paid on the day of the trip.",
    "Cancellazione gratuita fino a 24 ore prima della partenza con rimborso completo. Paghi il 30% oggi per prenotare; il saldo il giorno dell'escursione.",
  ),
  children: l(
    "Children under 4 travel free. Children 4 to 11 pay the child price. From 12 years the adult price applies.",
    "I bambini sotto i 4 anni viaggiano gratis. Dai 4 agli 11 anni pagano la tariffa bambino. Dai 12 anni si applica la tariffa adulto.",
  ),
};

const STANDARD_FAQ = [
  {
    question: l("Do I need to print my voucher?", "Devo stampare il voucher?"),
    answer: l("No. Show the voucher on your phone; we also send it by email and WhatsApp.", "No. Basta mostrare il voucher sul telefono; lo inviamo anche via email e WhatsApp."),
  },
  {
    question: l("How do I pay the balance?", "Come pago il saldo?"),
    answer: l("You pay 30% online today. The balance is paid in cash or by card to our representative on the day.", "Paghi il 30% online oggi. Il saldo si paga in contanti o con carta al nostro rappresentante il giorno stesso."),
  },
  {
    question: l("What if the weather is bad?", "E se il tempo è brutto?"),
    answer: l("If we cancel for weather or sea conditions you can move to another date or receive a full refund.", "Se annulliamo per meteo o condizioni del mare puoi cambiare data o ricevere il rimborso completo."),
  },
];

export interface MockTimelineItem {
  time_from?: string;
  time_to?: string;
  title: L;
  description: L;
  place?: string;
  transfer_included?: boolean;
}

export interface MockTrip {
  id: number;
  slug: string;
  type: TripType;
  title: L;
  summary: L;
  description: L;
  destination: string;
  route?: { name: L; days: number }[];
  image: string;
  gallery: string[];
  duration_hours?: number;
  days?: number;
  nights?: number;
  rating: number;
  reviews_count: number;
  pricing_mode: "per_person" | "per_unit";
  unit_label?: L;
  booking_types: BookingType[];
  badges: TripBadge[];
  featured: boolean;
  created_at: string;
  highlights: L[];
  included: L[];
  excluded: L[];
  what_to_bring: L[];
  timeline: { day_number: number; title?: L; items: MockTimelineItem[] }[];
  meeting_point: { text: L; lat: number; lng: number; label: string };
  min_persons: number;
  max_persons: number;
  base_price: number;
  base_child_price: number;
  private_tiers: { id: number; min_people: number; max_people: number; price_per_person: number }[];
  unit_options: { id: number; name: L; seats: number; price: number; max_quantity: number; children_allowed: boolean }[];
  price_list: MockSeason[];
  addons: MockAddon[];
  group_weekdays: number[];
  deposit_percent: number;
  free_cancellation_hours: number;
  guide_languages: string[];
  vessel?: {
    name: string;
    itinerary_from: string;
    itinerary_to: string;
    facilities: L[];
    meal_type: L;
    cabins: { type: L; stars: number; description: L }[];
  };
  stages?: { destination: L; days: number; nights: number; description: L; accommodation?: L }[];
  transfers_included: boolean;
}

const pickup = (time: string, place = "Hotel lobby"): MockTimelineItem => ({
  time_from: time,
  title: l("Hotel pick-up", "Prelievo in hotel"),
  description: l("Your driver meets you in the hotel lobby with a name sign.", "L'autista ti aspetta nella hall dell'hotel con un cartello con il tuo nome."),
  place,
  transfer_included: true,
});
const dropoff = (time: string): MockTimelineItem => ({
  time_from: time,
  title: l("Drop-off at your hotel", "Rientro in hotel"),
  description: l("Relax in the vehicle on the way back to your hotel.", "Rilassati in auto durante il rientro in hotel."),
  transfer_included: true,
});

export const MOCK_TRIPS: MockTrip[] = [
  {
    id: 1,
    slug: "pyramids-giza-sphinx-egyptian-museum",
    type: "single_day",
    title: l("Pyramids of Giza, Sphinx and Egyptian Museum Day Tour", "Piramidi di Giza, Sfinge e Museo Egizio: tour di un giorno"),
    summary: l("A full day with a licensed Egyptologist at Giza and the Egyptian Museum, lunch by the Nile.", "Una giornata con un egittologo a Giza e al Museo Egizio, pranzo sul Nilo."),
    description: l(
      "Spend a full day with a licensed Egyptologist at the Giza plateau and the Egyptian Museum. Hotel pick-up and drop-off, lunch by the Nile and a comfortable air-conditioned vehicle are included. Choose a private tour for your party or join a small group.",
      "Trascorri una giornata intera con un egittologo autorizzato sulla piana di Giza e al Museo Egizio. Prelievo e rientro in hotel, pranzo sul Nilo e un comodo veicolo climatizzato sono inclusi. Scegli un tour privato per il tuo gruppo o unisciti a un piccolo gruppo.",
    ),
    destination: "cairo",
    image: "/images/cairo.webp",
    gallery: ["/images/cairo.webp", "/images/luxor.webp", "/images/balloons.webp", "/images/aswan.webp", "/images/nile-cruise.webp", "/images/alexandria.webp"],
    duration_hours: 9,
    rating: 4.8,
    reviews_count: 312,
    pricing_mode: "per_person",
    booking_types: ["private", "group"],
    badges: ["free_cancellation", "best_seller"],
    featured: true,
    created_at: "2026-02-10",
    highlights: [
      l("See the Great Pyramids of Giza and the Sphinx up close", "Ammira da vicino le Grandi Piramidi di Giza e la Sfinge"),
      l("Explore the Egyptian Museum with a licensed Egyptologist", "Visita il Museo Egizio con un egittologo autorizzato"),
      l("Enjoy a relaxed lunch by the Nile", "Goditi un pranzo rilassante sul Nilo"),
      l("Air-conditioned transport, hotel to hotel", "Trasporto climatizzato, da hotel a hotel"),
    ],
    included: [
      l("Hotel pick-up and drop-off in an air-conditioned vehicle", "Prelievo e rientro in hotel con veicolo climatizzato"),
      l("English or Italian-speaking Egyptologist guide", "Guida egittologa in inglese o italiano"),
      l("Entrance fees: Giza plateau, Sphinx and Egyptian Museum", "Ingressi: piana di Giza, Sfinge e Museo Egizio"),
      l("Lunch by the Nile", "Pranzo sul Nilo"),
      l("Bottled water on the vehicle", "Acqua in bottiglia a bordo"),
    ],
    excluded: [
      l("Tips and gratuities", "Mance"),
      l("Camel or horse ride at the plateau", "Giro in cammello o a cavallo"),
      l("Entry inside the pyramids (optional ticket)", "Ingresso all'interno delle piramidi (biglietto opzionale)"),
      l("Drinks at lunch", "Bevande a pranzo"),
      l("Personal expenses", "Spese personali"),
    ],
    what_to_bring: [
      l("Comfortable walking shoes", "Scarpe comode"),
      l("Sunscreen and water bottle", "Crema solare e borraccia"),
      l("Hat and sunglasses", "Cappello e occhiali da sole"),
      l("Passport or ID copy for museum entry", "Copia del passaporto per il museo"),
    ],
    timeline: [
      {
        day_number: 1,
        items: [
          { ...pickup("08:00"), description: l("Your driver meets you in the hotel lobby in Cairo or Giza with a name sign.", "L'autista ti aspetta nella hall dell'hotel al Cairo o a Giza con un cartello.") },
          { time_from: "09:30", title: l("Giza plateau", "Piana di Giza"), description: l("Explore the Great Pyramid, the Sphinx and the panoramic viewpoint with your Egyptologist.", "Esplora la Grande Piramide, la Sfinge e il belvedere con il tuo egittologo.") },
          { time_from: "12:30", title: l("Lunch by the Nile", "Pranzo sul Nilo"), description: l("A relaxed lunch at a riverside restaurant with views of the water.", "Un pranzo rilassato in un ristorante sul fiume.") },
          { time_from: "14:30", title: l("Egyptian Museum", "Museo Egizio"), description: l("See the Tutankhamun treasures and the Royal Mummies with expert commentary.", "Ammira i tesori di Tutankhamon e le mummie reali con un esperto.") },
          dropoff("17:00"),
        ],
      },
    ],
    meeting_point: {
      text: l(
        "Pick-up from your hotel lobby in Cairo or Giza between 08:00 and 08:30. If your hotel is not listed, meet at the Giza Plateau main gate.",
        "Prelievo nella hall del tuo hotel al Cairo o a Giza tra le 08:00 e le 08:30. Se il tuo hotel non è in elenco, ritrovo all'ingresso principale della piana di Giza.",
      ),
      lat: 29.9792,
      lng: 31.1342,
      label: "Pick-up area · Giza",
    },
    min_persons: 1,
    max_persons: 12,
    base_price: 60,
    base_child_price: 30,
    private_tiers: [
      { id: 11, min_people: 1, max_people: 1, price_per_person: 140 },
      { id: 12, min_people: 2, max_people: 2, price_per_person: 85 },
      { id: 13, min_people: 3, max_people: 4, price_per_person: 70 },
      { id: 14, min_people: 5, max_people: 8, price_per_person: 60 },
      { id: 15, min_people: 9, max_people: 12, price_per_person: 52 },
    ],
    unit_options: [],
    price_list: seasons({ adult: 60, child: 30, tiers: { "11": 140, "12": 85, "13": 70, "14": 60, "15": 52 } }, 100),
    addons: [ADDONS.guide, ADDONS.lunch],
    group_weekdays: [1, 4],
    deposit_percent: 30,
    free_cancellation_hours: 24,
    guide_languages: ["English", "Italiano"],
    transfers_included: false,
  },
  {
    id: 2,
    slug: "ras-mohammed-snorkelling",
    type: "single_day",
    title: l("Ras Mohammed Snorkelling Boat Trip", "Ras Mohammed: escursione in barca con snorkeling"),
    summary: l("Two reef stops in the national park, lunch on board and a marine guide.", "Due soste sulla barriera del parco nazionale, pranzo a bordo e guida marina."),
    description: l(
      "Sail from Sharm El Sheikh marina to Ras Mohammed National Park, the jewel of the Red Sea. Snorkel two of the most famous reefs with a marine guide, enjoy lunch on board and relax on the sun deck between stops.",
      "Salpa dal porto di Sharm El Sheikh verso il Parco Nazionale di Ras Mohammed, il gioiello del Mar Rosso. Fai snorkeling su due delle barriere più famose con una guida marina, pranzo a bordo e relax sul ponte tra una sosta e l'altra.",
    ),
    destination: "sharm-el-sheikh",
    image: "/images/sharm.webp",
    gallery: ["/images/sharm.webp", "/images/hurghada.webp", "/images/alexandria.webp", "/images/aswan.webp"],
    duration_hours: 8,
    rating: 4.9,
    reviews_count: 488,
    pricing_mode: "per_person",
    booking_types: ["group", "private"],
    badges: ["free_cancellation"],
    featured: true,
    created_at: "2026-03-02",
    highlights: [
      l("Snorkel Shark & Yolanda reef and the Magic Pool", "Snorkeling a Shark & Yolanda reef e nella Magic Pool"),
      l("Marine guide in the water with you", "Guida marina in acqua con te"),
      l("Buffet lunch and soft drinks on board", "Pranzo a buffet e bibite a bordo"),
      l("National park fees included", "Ingresso al parco nazionale incluso"),
    ],
    included: [
      l("Hotel pick-up and drop-off", "Prelievo e rientro in hotel"),
      l("Boat trip with two snorkelling stops", "Gita in barca con due soste di snorkeling"),
      l("Buffet lunch, water and soft drinks", "Pranzo a buffet, acqua e bibite"),
      l("Ras Mohammed park fee", "Ingresso al parco di Ras Mohammed"),
    ],
    excluded: [l("Snorkelling gear (rental available)", "Attrezzatura (noleggio disponibile)"), l("Tips", "Mance"), l("Underwater photos", "Foto subacquee")],
    what_to_bring: [l("Swimwear and towel", "Costume e asciugamano"), l("Reef-safe sunscreen", "Crema solare reef-safe"), l("Passport copy", "Copia del passaporto")],
    timeline: [
      {
        day_number: 1,
        items: [
          pickup("07:30"),
          { time_from: "08:30", title: l("Boarding at the marina", "Imbarco al porto"), description: l("Safety briefing and departure towards the national park.", "Briefing di sicurezza e partenza verso il parco.") },
          { time_from: "10:00", title: l("First reef stop", "Prima sosta"), description: l("Snorkel Shark & Yolanda reef with the marine guide.", "Snorkeling a Shark & Yolanda reef con la guida.") },
          { time_from: "12:30", title: l("Lunch on board", "Pranzo a bordo"), description: l("Buffet lunch while the boat moves to the second reef.", "Pranzo a buffet durante lo spostamento.") },
          { time_from: "13:30", title: l("Magic Pool", "Magic Pool"), description: l("Calm turquoise water, perfect for beginners.", "Acque calme e turchesi, ideali per i principianti.") },
          dropoff("16:00"),
        ],
      },
    ],
    meeting_point: { text: l("Pick-up from hotels in Sharm El Sheikh and Naama Bay.", "Prelievo dagli hotel di Sharm El Sheikh e Naama Bay."), lat: 27.7333, lng: 34.2531, label: "Travco marina · Sharm" },
    min_persons: 1,
    max_persons: 12,
    base_price: 35,
    base_child_price: 18,
    private_tiers: [
      { id: 21, min_people: 1, max_people: 4, price_per_person: 70 },
      { id: 22, min_people: 5, max_people: 12, price_per_person: 55 },
    ],
    unit_options: [],
    price_list: seasons({ adult: 35, child: 18, tiers: { "21": 70, "22": 55 }, explicit: { 1: 25, 2: 30, 3: 28, 4: 35, 5: 45 } }, 200),
    addons: [ADDONS.fins, ADDONS.photos],
    group_weekdays: [0, 1, 2, 3, 4, 5, 6],
    deposit_percent: 30,
    free_cancellation_hours: 24,
    guide_languages: ["English", "Italiano", "Deutsch"],
    transfers_included: false,
  },
  {
    id: 3,
    slug: "desert-buggy-safari",
    type: "single_day",
    title: l("Desert Buggy Safari at Sunset", "Safari in buggy nel deserto al tramonto"),
    summary: l("Drive your own buggy through the Sinai desert, tea in a Bedouin camp.", "Guida il tuo buggy nel deserto del Sinai, tè in un campo beduino."),
    description: l(
      "Ride your own dune buggy across the Sinai desert with an experienced leader, stop at a Bedouin camp for tea and watch the sunset over the mountains. Choose single, double or family buggies; every buggy needs at least one adult driver.",
      "Guida il tuo buggy attraverso il deserto del Sinai con una guida esperta, fermati in un campo beduino per il tè e ammira il tramonto sulle montagne. Scegli buggy singoli, doppi o famiglia; ogni buggy richiede almeno un adulto alla guida.",
    ),
    destination: "sharm-el-sheikh",
    image: "/images/hurghada.webp",
    gallery: ["/images/hurghada.webp", "/images/sharm.webp", "/images/cairo.webp"],
    duration_hours: 4,
    rating: 4.7,
    reviews_count: 221,
    pricing_mode: "per_unit",
    unit_label: l("buggy", "buggy"),
    booking_types: ["private", "group"],
    badges: ["free_cancellation", "deposit_only"],
    featured: true,
    created_at: "2026-04-15",
    highlights: [
      l("Drive your own buggy on desert tracks", "Guida il tuo buggy sulle piste del deserto"),
      l("Sunset stop with panoramic views", "Sosta al tramonto con vista panoramica"),
      l("Bedouin tea and bread in a desert camp", "Tè e pane beduino in un campo nel deserto"),
    ],
    included: [l("Hotel pick-up and drop-off", "Prelievo e rientro in hotel"), l("Buggy, fuel and safety briefing", "Buggy, carburante e briefing di sicurezza"), l("Bedouin tea", "Tè beduino")],
    excluded: [l("Helmet and goggles (optional)", "Casco e occhiali (opzionali)"), l("Dinner", "Cena"), l("Tips", "Mance")],
    what_to_bring: [l("Closed shoes", "Scarpe chiuse"), l("Scarf for the dust", "Sciarpa per la polvere"), l("Driving licence not required", "Patente non necessaria")],
    timeline: [
      {
        day_number: 1,
        items: [
          pickup("14:00"),
          { time_from: "14:45", title: l("Safety briefing", "Briefing di sicurezza"), description: l("Meet your leader and test your buggy.", "Incontra la guida e prova il tuo buggy.") },
          { time_from: "15:00", title: l("Desert ride", "Giro nel deserto"), description: l("About 90 minutes on desert tracks in convoy.", "Circa 90 minuti sulle piste in convoglio.") },
          { time_from: "16:45", title: l("Bedouin camp and sunset", "Campo beduino e tramonto"), description: l("Tea, bread baked on the fire and the sunset.", "Tè, pane cotto sul fuoco e tramonto.") },
          dropoff("18:30"),
        ],
      },
    ],
    meeting_point: { text: l("Pick-up from hotels in Sharm El Sheikh.", "Prelievo dagli hotel di Sharm El Sheikh."), lat: 27.9158, lng: 34.33, label: "Safari base · Sharm" },
    min_persons: 1,
    max_persons: 16,
    base_price: 35,
    base_child_price: 0,
    private_tiers: [],
    unit_options: [
      { id: 31, name: l("Single buggy", "Buggy singolo"), seats: 1, price: 35, max_quantity: 6, children_allowed: false },
      { id: 32, name: l("Double buggy", "Buggy doppio"), seats: 2, price: 50, max_quantity: 6, children_allowed: true },
      { id: 33, name: l("Family buggy", "Buggy famiglia"), seats: 4, price: 90, max_quantity: 3, children_allowed: true },
    ],
    price_list: seasons({ units: { "31": 35, "32": 50, "33": 90 } }, 300),
    addons: [ADDONS.goggles, ADDONS.bedouinDinner],
    group_weekdays: [0, 1, 2, 3, 4, 5, 6],
    deposit_percent: 30,
    free_cancellation_hours: 24,
    guide_languages: ["English", "Italiano"],
    transfers_included: false,
  },
  {
    id: 4,
    slug: "luxor-highlights-3-days",
    type: "multi_day",
    title: l("Luxor Highlights, 3 days 2 nights", "Il meglio di Luxor, 3 giorni 2 notti"),
    summary: l("Karnak, Luxor Temple, the Valley of the Kings and Hatshepsut.", "Karnak, il Tempio di Luxor, la Valle dei Re e Hatshepsut."),
    description: l(
      "Three unhurried days on both banks of the Nile with an Egyptologist: the temples of Karnak and Luxor, the Valley of the Kings, the Temple of Hatshepsut and the Colossi of Memnon.",
      "Tre giorni senza fretta sulle due rive del Nilo con un egittologo: i templi di Karnak e Luxor, la Valle dei Re, il Tempio di Hatshepsut e i Colossi di Memnone.",
    ),
    destination: "luxor",
    image: "/images/luxor.webp",
    gallery: ["/images/luxor.webp", "/images/balloons.webp", "/images/nile-cruise.webp", "/images/aswan.webp"],
    days: 3,
    nights: 2,
    rating: 4.7,
    reviews_count: 96,
    pricing_mode: "per_person",
    booking_types: ["private", "group"],
    badges: ["deposit_only"],
    featured: true,
    created_at: "2026-05-01",
    highlights: [
      l("Karnak and Luxor temples with an Egyptologist", "I templi di Karnak e Luxor con un egittologo"),
      l("Three tombs in the Valley of the Kings", "Tre tombe nella Valle dei Re"),
      l("Optional sunrise hot-air balloon", "Mongolfiera all'alba facoltativa"),
    ],
    included: [l("2 nights in a 4-star hotel with breakfast", "2 notti in hotel 4 stelle con colazione"), l("All transfers in Luxor", "Tutti i trasferimenti a Luxor"), l("Entrance fees in the programme", "Ingressi del programma"), l("Egyptologist guide", "Guida egittologa")],
    excluded: [l("Flights to Luxor", "Voli per Luxor"), l("Lunches and dinners", "Pranzi e cene"), l("Tips", "Mance")],
    what_to_bring: [l("Comfortable shoes", "Scarpe comode"), l("Hat and sunscreen", "Cappello e crema solare")],
    timeline: [
      { day_number: 1, title: l("East Bank temples", "I templi della riva orientale"), items: [pickup("09:00"), { time_from: "10:00", title: l("Karnak Temple", "Tempio di Karnak"), description: l("The largest temple complex of ancient Egypt.", "Il più grande complesso templare dell'antico Egitto.") }, { time_from: "18:00", title: l("Luxor Temple by night", "Tempio di Luxor di sera"), description: l("The temple lit up after sunset.", "Il tempio illuminato dopo il tramonto.") }] },
      { day_number: 2, title: l("West Bank", "La riva occidentale"), items: [{ time_from: "07:30", title: l("Valley of the Kings", "Valle dei Re"), description: l("Three royal tombs with your guide.", "Tre tombe reali con la guida.") }, { time_from: "11:00", title: l("Temple of Hatshepsut", "Tempio di Hatshepsut"), description: l("The terraced temple at Deir el-Bahari.", "Il tempio a terrazze di Deir el-Bahari.") }] },
      { day_number: 3, title: l("Departure", "Partenza"), items: [{ time_from: "09:00", title: l("Free morning", "Mattinata libera"), description: l("Relax or add the balloon flight.", "Relax o volo in mongolfiera.") }, dropoff("12:00")] },
    ],
    meeting_point: { text: l("Pick-up from Luxor airport, train station or hotel.", "Prelievo all'aeroporto, alla stazione o in hotel a Luxor."), lat: 25.6872, lng: 32.6396, label: "Luxor" },
    min_persons: 1,
    max_persons: 12,
    base_price: 290,
    base_child_price: 180,
    private_tiers: [
      { id: 41, min_people: 1, max_people: 2, price_per_person: 290 },
      { id: 42, min_people: 3, max_people: 12, price_per_person: 250 },
    ],
    unit_options: [],
    price_list: seasons({ adult: 290, child: 180, tiers: { "41": 290, "42": 250 } }, 400),
    addons: [ADDONS.balloon, ADDONS.guide],
    group_weekdays: [1, 3, 5],
    deposit_percent: 30,
    free_cancellation_hours: 72,
    guide_languages: ["English", "Italiano"],
    transfers_included: false,
  },
  {
    id: 5,
    slug: "felucca-sunset-sail-aswan",
    type: "nile",
    title: l("Felucca Sunset Sail with Dinner, Aswan", "Tramonto in feluca con cena, Assuan"),
    summary: l("A traditional felucca around Elephantine Island, dinner in a Nubian house.", "Una feluca tradizionale intorno all'isola Elefantina, cena in una casa nubiana."),
    description: l(
      "Sail a traditional felucca around Elephantine Island and Kitchener's Island as the sun sets, then enjoy dinner in a Nubian house on the west bank.",
      "Naviga su una feluca tradizionale intorno all'isola Elefantina e all'isola di Kitchener al tramonto, poi cena in una casa nubiana sulla riva occidentale.",
    ),
    destination: "aswan",
    image: "/images/aswan.webp",
    gallery: ["/images/aswan.webp", "/images/nile-cruise.webp", "/images/luxor.webp"],
    duration_hours: 2,
    rating: 4.8,
    reviews_count: 205,
    pricing_mode: "per_person",
    booking_types: ["private", "group"],
    badges: ["free_cancellation"],
    featured: true,
    created_at: "2026-03-20",
    highlights: [l("Sunset on the Nile by felucca", "Tramonto sul Nilo in feluca"), l("Dinner with a Nubian family", "Cena con una famiglia nubiana")],
    included: [l("Felucca with captain", "Feluca con capitano"), l("Nubian dinner", "Cena nubiana"), l("Hotel transfers in Aswan", "Trasferimenti in hotel ad Assuan")],
    excluded: [l("Drinks", "Bevande"), l("Tips", "Mance")],
    what_to_bring: [l("A light jacket", "Una giacca leggera")],
    timeline: [{ day_number: 1, items: [pickup("16:00"), { time_from: "16:30", title: l("Felucca sail", "Navigazione in feluca"), description: l("Around Elephantine Island at sunset.", "Intorno all'isola Elefantina al tramonto.") }, { time_from: "18:00", title: l("Nubian dinner", "Cena nubiana"), description: l("Home-cooked dinner on the west bank.", "Cena casalinga sulla riva occidentale.") }, dropoff("20:00")] }],
    meeting_point: { text: l("Aswan corniche, in front of the Old Cataract hotel.", "Corniche di Assuan, davanti all'Old Cataract."), lat: 24.0889, lng: 32.8998, label: "Aswan corniche" },
    min_persons: 1,
    max_persons: 10,
    base_price: 38,
    base_child_price: 20,
    private_tiers: [{ id: 51, min_people: 1, max_people: 10, price_per_person: 55 }],
    unit_options: [],
    price_list: seasons({ adult: 38, child: 20, tiers: { "51": 55 } }, 500),
    addons: [ADDONS.lunch],
    group_weekdays: [0, 1, 2, 3, 4, 5, 6],
    deposit_percent: 30,
    free_cancellation_hours: 24,
    guide_languages: ["English", "Italiano"],
    transfers_included: false,
  },
  {
    id: 6,
    slug: "ms-nile-sun-cruise-luxor-aswan",
    type: "nile",
    title: l("M/S Nile Sun Cruise, Luxor to Aswan", "Crociera M/S Nile Sun, da Luxor ad Assuan"),
    summary: l("Four nights on M/S Nile Sun, full board, with temples and a guide at every stop.", "Quattro notti sulla M/S Nile Sun, pensione completa, templi e guida a ogni tappa."),
    description: l(
      "Four nights on M/S Nile Sun, full board, with temples, sunsets on the sun terrace and a guide at every stop. Sail from Luxor to Aswan visiting Karnak, the Valley of the Kings, Edfu, Kom Ombo and Philae.",
      "Quattro notti sulla M/S Nile Sun in pensione completa, con templi, tramonti sulla terrazza solarium e una guida a ogni tappa. Da Luxor ad Assuan visitando Karnak, la Valle dei Re, Edfu, Kom Ombo e Philae.",
    ),
    destination: "luxor",
    image: "/images/nile-cruise.webp",
    gallery: ["/images/nile-cruise.webp", "/images/aswan.webp", "/images/luxor.webp", "/images/balloons.webp"],
    days: 5,
    nights: 4,
    rating: 4.8,
    reviews_count: 174,
    pricing_mode: "per_person",
    booking_types: ["private", "group"],
    badges: ["free_cancellation", "best_seller"],
    featured: true,
    created_at: "2026-01-12",
    highlights: [
      l("Full board on a 5-star Nile cruiser", "Pensione completa su una motonave 5 stelle"),
      l("Karnak, Edfu, Kom Ombo and Philae with an Egyptologist", "Karnak, Edfu, Kom Ombo e Philae con un egittologo"),
      l("Sun terrace with pool and sunset views", "Terrazza solarium con piscina e tramonti"),
    ],
    included: [l("4 nights in the cabin type you choose", "4 notti nel tipo di cabina scelto"), l("Full board", "Pensione completa"), l("Guided visits and entrance fees", "Visite guidate e ingressi"), l("Transfers in Luxor and Aswan", "Trasferimenti a Luxor e Assuan")],
    excluded: [l("Drinks on board", "Bevande a bordo"), l("Abu Simbel excursion", "Escursione ad Abu Simbel"), l("Tips", "Mance")],
    what_to_bring: [l("Light clothes and a warm layer for the evenings", "Abiti leggeri e un capo caldo per la sera"), l("Swimwear for the sun terrace", "Costume per la terrazza solarium")],
    timeline: [
      { day_number: 1, title: l("Luxor, boarding", "Luxor, imbarco"), items: [{ time_from: "12:00", title: l("Boarding and lunch", "Imbarco e pranzo"), description: l("Welcome drink and cabin check-in.", "Drink di benvenuto e sistemazione.") , transfer_included: true }, { time_from: "15:00", title: l("Karnak and Luxor temples", "Templi di Karnak e Luxor"), description: l("Guided visit of the East Bank.", "Visita guidata della riva orientale.") }] },
      { day_number: 2, title: l("West Bank and sailing", "Riva occidentale e navigazione"), items: [{ time_from: "06:30", title: l("Valley of the Kings", "Valle dei Re"), description: l("Royal tombs and the Temple of Hatshepsut.", "Tombe reali e Tempio di Hatshepsut.") }, { time_from: "13:00", title: l("Sail to Edfu", "Navigazione verso Edfu"), description: l("Afternoon on the sun terrace.", "Pomeriggio sulla terrazza solarium.") }] },
      { day_number: 3, title: l("Edfu and Kom Ombo", "Edfu e Kom Ombo"), items: [{ time_from: "07:00", title: l("Temple of Horus", "Tempio di Horus"), description: l("The best preserved temple in Egypt.", "Il tempio meglio conservato d'Egitto.") }, { time_from: "17:00", title: l("Kom Ombo at sunset", "Kom Ombo al tramonto"), description: l("The double temple above the river.", "Il doppio tempio sul fiume.") }] },
      { day_number: 4, title: l("Aswan", "Assuan"), items: [{ time_from: "08:00", title: l("Philae and the High Dam", "Philae e l'Alta Diga"), description: l("Boat to the Temple of Isis.", "In barca al Tempio di Iside.") }, { time_from: "16:00", title: l("Felucca ride", "Giro in feluca"), description: l("Sunset around Elephantine Island.", "Tramonto intorno all'isola Elefantina.") }] },
      { day_number: 5, title: l("Disembarkation", "Sbarco"), items: [{ time_from: "08:00", title: l("Breakfast and check-out", "Colazione e sbarco"), description: l("Transfer to Aswan airport or station.", "Trasferimento all'aeroporto o alla stazione di Assuan."), transfer_included: true }] },
    ],
    meeting_point: { text: l("Boarding at the Luxor cruise dock; transfers from Luxor airport or hotels included.", "Imbarco al molo crociere di Luxor; trasferimenti da aeroporto o hotel inclusi."), lat: 25.7, lng: 32.64, label: "Luxor cruise dock" },
    min_persons: 1,
    max_persons: 8,
    base_price: 480,
    base_child_price: 300,
    private_tiers: [{ id: 61, min_people: 1, max_people: 8, price_per_person: 620 }],
    unit_options: [],
    price_list: seasons({ adult: 480, child: 300, tiers: { "61": 620 } }, 600),
    addons: [ADDONS.balloon],
    group_weekdays: [1, 5],
    deposit_percent: 30,
    free_cancellation_hours: 168,
    guide_languages: ["English", "Italiano", "Français"],
    vessel: {
      name: "M/S Nile Sun",
      itinerary_from: "Luxor",
      itinerary_to: "Aswan",
      facilities: [l("Pool", "Piscina"), l("Restaurant", "Ristorante"), l("Sun terrace", "Terrazza solarium"), l("Wi-Fi", "Wi-Fi"), l("Bar", "Bar")],
      meal_type: l("Full board", "Pensione completa"),
      cabins: [
        { type: l("Standard cabin", "Cabina standard"), stars: 5, description: l("Twin or double, panoramic window", "Doppia o matrimoniale, finestra panoramica") },
        { type: l("Superior cabin", "Cabina superior"), stars: 5, description: l("Larger cabin with sitting area", "Cabina più ampia con salottino") },
        { type: l("Suite", "Suite"), stars: 5, description: l("Private balcony and bathtub", "Balcone privato e vasca") },
      ],
    },
    transfers_included: false,
  },
  {
    id: 7,
    slug: "giftun-island-snorkelling",
    type: "single_day",
    title: l("Giftun Island Snorkelling Boat Trip", "Isola di Giftun: gita in barca con snorkeling"),
    summary: l("Beach time on Giftun Island and two snorkelling stops.", "Spiaggia all'isola di Giftun e due soste di snorkeling."),
    description: l("A relaxed day on the Red Sea from Hurghada: white sand on Giftun Island and two coral reef stops.", "Una giornata rilassante sul Mar Rosso da Hurghada: sabbia bianca a Giftun e due soste sulla barriera."),
    destination: "hurghada",
    image: "/images/hurghada.webp",
    gallery: ["/images/hurghada.webp", "/images/sharm.webp"],
    duration_hours: 8,
    rating: 4.7,
    reviews_count: 540,
    pricing_mode: "per_person",
    booking_types: ["group", "private"],
    badges: ["best_seller"],
    featured: false,
    created_at: "2026-06-01",
    highlights: [l("White sand beach on Giftun", "Spiaggia bianca a Giftun"), l("Two reef stops", "Due soste sulla barriera")],
    included: [l("Hotel transfers", "Trasferimenti in hotel"), l("Lunch on board", "Pranzo a bordo"), l("Park fee", "Ingresso al parco")],
    excluded: [l("Snorkelling gear", "Attrezzatura"), l("Tips", "Mance")],
    what_to_bring: [l("Swimwear and towel", "Costume e asciugamano")],
    timeline: [{ day_number: 1, items: [pickup("08:00"), { time_from: "09:00", title: l("Sail to Giftun", "Navigazione verso Giftun"), description: l("Snorkelling and beach.", "Snorkeling e spiaggia.") }, dropoff("16:30")] }],
    meeting_point: { text: l("Pick-up from Hurghada hotels.", "Prelievo dagli hotel di Hurghada."), lat: 27.2579, lng: 33.8116, label: "Hurghada marina" },
    min_persons: 1,
    max_persons: 12,
    base_price: 35,
    base_child_price: 18,
    private_tiers: [{ id: 71, min_people: 1, max_people: 12, price_per_person: 60 }],
    unit_options: [],
    price_list: seasons({ adult: 35, child: 18, tiers: { "71": 60 } }, 700),
    addons: [ADDONS.fins],
    group_weekdays: [0, 1, 2, 3, 4, 5, 6],
    deposit_percent: 30,
    free_cancellation_hours: 24,
    guide_languages: ["English", "Italiano"],
    transfers_included: false,
  },
];

const packageBase = (o: Partial<MockTrip> & Pick<MockTrip, "id" | "slug" | "title" | "summary" | "description" | "image" | "days" | "nights" | "base_price" | "route" | "stages" | "rating" | "reviews_count">): MockTrip => ({
  type: "package",
  destination: "cairo",
  gallery: [o.image, "/images/luxor.webp", "/images/aswan.webp", "/images/nile-cruise.webp", "/images/hurghada.webp"],
  pricing_mode: "per_person",
  booking_types: ["private", "group"],
  badges: ["transfers_included"],
  featured: true,
  created_at: "2026-02-01",
  highlights: [
    l("Hotels, guides and entrance fees in one price", "Hotel, guide e ingressi in un unico prezzo"),
    l("Airport and inter-city transfers included", "Trasferimenti da aeroporto e tra città inclusi"),
    l("Egyptologist guide at every stage", "Guida egittologa in ogni tappa"),
  ],
  included: [
    l("Airport pick-up and drop-off", "Prelievo e rientro in aeroporto"),
    l("Transfers between stages", "Trasferimenti tra le tappe"),
    l("Hotels with breakfast", "Hotel con colazione"),
    l("Guided visits and entrance fees", "Visite guidate e ingressi"),
  ],
  excluded: [l("International flights", "Voli internazionali"), l("Visa", "Visto"), l("Tips", "Mance")],
  what_to_bring: [l("Passport valid for 6 months", "Passaporto valido 6 mesi"), l("Comfortable shoes", "Scarpe comode")],
  timeline: [],
  meeting_point: { text: l("Arrivals hall of Cairo International Airport.", "Sala arrivi dell'aeroporto internazionale del Cairo."), lat: 30.1219, lng: 31.4056, label: "Cairo Airport (CAI)" },
  min_persons: 1,
  max_persons: 12,
  base_child_price: Math.round(o.base_price * 0.6),
  private_tiers: [{ id: o.id * 10 + 1, min_people: 1, max_people: 12, price_per_person: Math.round(o.base_price * 1.25) }],
  unit_options: [],
  price_list: seasons({ adult: o.base_price, child: Math.round(o.base_price * 0.6), tiers: { [String(o.id * 10 + 1)]: Math.round(o.base_price * 1.25) } }, o.id * 100),
  addons: [ADDONS.balloon, ADDONS.guide],
  group_weekdays: [6],
  deposit_percent: 30,
  free_cancellation_hours: 336,
  guide_languages: ["English", "Italiano"],
  transfers_included: true,
  ...o,
});

export const MOCK_PACKAGES: MockTrip[] = [
  packageBase({
    id: 8,
    slug: "classic-egypt-8-days",
    title: l("Classic Egypt, 8 days", "Egitto classico, 8 giorni"),
    summary: l("Cairo, Luxor and Aswan with hotels, guides and every transfer included.", "Il Cairo, Luxor e Assuan con hotel, guide e tutti i trasferimenti inclusi."),
    description: l(
      "The essential Egypt in eight days: the pyramids and the Egyptian Museum in Cairo, the temples and tombs of Luxor, then Philae, Nile sunsets and Abu Simbel from Aswan. Airport and inter-city transfers are part of the package.",
      "L'Egitto essenziale in otto giorni: piramidi e Museo Egizio al Cairo, templi e tombe di Luxor, poi Philae, tramonti sul Nilo e Abu Simbel da Assuan. I trasferimenti da aeroporto e tra le città sono inclusi.",
    ),
    image: "/images/cairo.webp",
    days: 8,
    nights: 7,
    base_price: 820,
    rating: 4.9,
    reviews_count: 128,
    route: [
      { name: l("Cairo", "Il Cairo"), days: 3 },
      { name: l("Luxor", "Luxor"), days: 2 },
      { name: l("Aswan", "Assuan"), days: 3 },
    ],
    stages: [
      { destination: l("Cairo", "Il Cairo"), days: 3, nights: 3, description: l("Pyramids, Sphinx and the Egyptian Museum.", "Piramidi, Sfinge e Museo Egizio."), accommodation: l("4-star hotel in Giza", "Hotel 4 stelle a Giza") },
      { destination: l("Luxor", "Luxor"), days: 2, nights: 2, description: l("Karnak and the Valley of the Kings.", "Karnak e la Valle dei Re."), accommodation: l("Hotel Nile View", "Hotel Nile View") },
      { destination: l("Aswan", "Assuan"), days: 3, nights: 2, description: l("Philae, Nile sunsets and Abu Simbel.", "Philae, tramonti sul Nilo e Abu Simbel."), accommodation: l("Nile-front hotel", "Hotel sul Nilo") },
    ],
    timeline: [
      { day_number: 1, title: l("Arrival in Cairo", "Arrivo al Cairo"), items: [{ title: l("Airport pick-up", "Prelievo in aeroporto"), description: l("Meet and greet in the arrivals hall, transfer to your hotel.", "Accoglienza agli arrivi e trasferimento in hotel."), transfer_included: true }] },
      { day_number: 2, title: l("Pyramids and museum", "Piramidi e museo"), items: [{ title: l("Pyramids of Giza and Sphinx", "Piramidi di Giza e Sfinge"), description: l("Full day with your Egyptologist.", "Giornata intera con il tuo egittologo.") }] },
      { day_number: 4, title: l("Fly to Luxor", "Volo per Luxor"), items: [{ title: l("Transfer to Luxor", "Trasferimento a Luxor"), description: l("Domestic flight and transfer to the hotel.", "Volo interno e trasferimento in hotel."), transfer_included: true }, { title: l("Karnak Temple", "Tempio di Karnak"), description: l("Afternoon visit.", "Visita pomeridiana.") }] },
      { day_number: 6, title: l("Drive to Aswan", "Verso Assuan"), items: [{ title: l("Transfer to Aswan", "Trasferimento ad Assuan"), description: l("Private car with stops at Edfu and Kom Ombo.", "Auto privata con soste a Edfu e Kom Ombo."), transfer_included: true }] },
      { day_number: 8, title: l("Departure", "Partenza"), items: [{ title: l("Airport drop-off", "Trasferimento in aeroporto"), description: l("Transfer to Aswan airport.", "Trasferimento all'aeroporto di Assuan."), transfer_included: true }] },
    ],
  }),
  packageBase({
    id: 9,
    slug: "cairo-nile-cruise-red-sea-9-days",
    title: l("Cairo, Nile Cruise and Red Sea, 9 days", "Il Cairo, crociera sul Nilo e Mar Rosso, 9 giorni"),
    summary: l("Pyramids, a 4-night Nile cruise and beach days in Hurghada.", "Piramidi, crociera di 4 notti sul Nilo e mare a Hurghada."),
    description: l("Combine Cairo, a Nile cruise from Luxor to Aswan and three days of sea in Hurghada.", "Combina Il Cairo, una crociera sul Nilo da Luxor ad Assuan e tre giorni di mare a Hurghada."),
    image: "/images/hurghada.webp",
    days: 9,
    nights: 8,
    base_price: 1150,
    rating: 4.8,
    reviews_count: 86,
    route: [
      { name: l("Cairo", "Il Cairo"), days: 2 },
      { name: l("Nile", "Nilo"), days: 4 },
      { name: l("Hurghada", "Hurghada"), days: 3 },
    ],
    stages: [
      { destination: l("Cairo", "Il Cairo"), days: 2, nights: 2, description: l("Pyramids and the old city.", "Piramidi e città vecchia.") },
      { destination: l("Nile cruise", "Crociera sul Nilo"), days: 4, nights: 4, description: l("Luxor to Aswan on M/S Nile Sun.", "Da Luxor ad Assuan sulla M/S Nile Sun.") },
      { destination: l("Hurghada", "Hurghada"), days: 3, nights: 2, description: l("Beach, reefs and Giftun Island.", "Spiaggia, barriera e isola di Giftun.") },
    ],
  }),
  packageBase({
    id: 10,
    slug: "luxor-aswan-escape-5-days",
    title: l("Luxor and Aswan Escape, 5 days", "Fuga a Luxor e Assuan, 5 giorni"),
    summary: l("Temples, tombs and felucca sunsets in five relaxed days.", "Templi, tombe e tramonti in feluca in cinque giorni rilassati."),
    description: l("Five days in Upper Egypt with the best of Luxor and Aswan.", "Cinque giorni nell'Alto Egitto con il meglio di Luxor e Assuan."),
    image: "/images/luxor.webp",
    days: 5,
    nights: 4,
    base_price: 540,
    rating: 4.8,
    reviews_count: 64,
    route: [
      { name: l("Luxor", "Luxor"), days: 2 },
      { name: l("Aswan", "Assuan"), days: 3 },
    ],
    stages: [
      { destination: l("Luxor", "Luxor"), days: 2, nights: 2, description: l("Karnak and the West Bank.", "Karnak e la riva occidentale.") },
      { destination: l("Aswan", "Assuan"), days: 3, nights: 2, description: l("Philae and a felucca sunset.", "Philae e tramonto in feluca.") },
    ],
  }),
];

export interface MockBuilding {
  id: number;
  slug: string;
  name: string;
  type: L;
  stars: number;
  location: L;
  destination: string;
  address: string;
  lat: number;
  lng: number;
  description: L;
  amenities: L[];
  check_in_from: string;
  check_out_until: string;
  cancellation_policy: L;
  taxes_included: boolean;
  tax_per_night: number;
  deposit_percent: number;
  image: string;
}

export const MOCK_BUILDINGS: MockBuilding[] = [
  {
    id: 1,
    slug: "hotel-nile-view",
    name: "Hotel Nile View",
    type: l("Hotel", "Hotel"),
    stars: 4,
    location: l("Luxor East Bank", "Luxor, riva orientale"),
    destination: "luxor",
    address: "Corniche El Nil, Luxor 85951, Egypt",
    lat: 25.6995,
    lng: 32.6368,
    description: l("A boutique hotel on the Corniche with Nile-view terraces, five minutes from Luxor Temple.", "Un boutique hotel sulla Corniche con terrazze sul Nilo, a cinque minuti dal Tempio di Luxor."),
    amenities: [l("Rooftop pool", "Piscina sul tetto"), l("Free Wi-Fi", "Wi-Fi gratuito"), l("Breakfast", "Colazione"), l("Airport transfer", "Transfer aeroporto"), l("24h reception", "Reception 24h")],
    check_in_from: "14:00",
    check_out_until: "12:00",
    cancellation_policy: l("Free cancellation up to 7 days before check-in. 50% refund from 7 to 2 days. No refund within 48 hours.", "Cancellazione gratuita fino a 7 giorni prima. Rimborso del 50% da 7 a 2 giorni. Nessun rimborso nelle 48 ore."),
    taxes_included: true,
    tax_per_night: 0,
    deposit_percent: 30,
    image: "/images/room-deluxe.webp",
  },
  {
    id: 2,
    slug: "red-sea-coral-resort",
    name: "Red Sea Coral Resort",
    type: l("Resort", "Resort"),
    stars: 4,
    location: l("Hurghada", "Hurghada"),
    destination: "hurghada",
    address: "Sheraton Road, Hurghada, Egypt",
    lat: 27.2,
    lng: 33.84,
    description: l("Beachfront resort with a house reef and three pools.", "Resort sulla spiaggia con barriera privata e tre piscine."),
    amenities: [l("Private beach", "Spiaggia privata"), l("House reef", "Barriera corallina"), l("Free Wi-Fi", "Wi-Fi gratuito"), l("Breakfast", "Colazione")],
    check_in_from: "14:00",
    check_out_until: "12:00",
    cancellation_policy: l("Free cancellation up to 3 days before check-in.", "Cancellazione gratuita fino a 3 giorni prima del check-in."),
    taxes_included: true,
    tax_per_night: 0,
    deposit_percent: 30,
    image: "/images/hurghada.webp",
  },
];

export interface MockRoom {
  id: number;
  building_id: number;
  name: L;
  room_type: string;
  type_label: L;
  description: L;
  image: string;
  gallery: string[];
  max_adults: number;
  max_children: number;
  max_total: number;
  beds: number;
  bed_label: L;
  size_m2: number;
  view?: L;
  amenities: L[];
  price_per_night: number;
  free_cancellation: boolean;
  breakfast_included: boolean;
  few_left: boolean;
  min_nights: number;
  max_nights: number;
  extra_bed_price: number;
  price_list: MockSeason[];
}

const roomAmenities = [l("Wi-Fi", "Wi-Fi"), l("Breakfast", "Colazione"), l("Nile view", "Vista Nilo")];

export const MOCK_ROOMS: MockRoom[] = [
  {
    id: 1,
    building_id: 1,
    name: l("Deluxe Double", "Doppia Deluxe"),
    room_type: "double",
    type_label: l("Double room, king bed", "Camera doppia, letto king"),
    description: l("A bright room with a king bed, a balcony over the Nile and a rain shower.", "Una camera luminosa con letto king, balcone sul Nilo e doccia a pioggia."),
    image: "/images/room-deluxe.webp",
    gallery: ["/images/room-deluxe.webp", "/images/room-family.webp", "/images/nile-cruise.webp", "/images/luxor.webp"],
    max_adults: 2,
    max_children: 1,
    max_total: 3,
    beds: 1,
    bed_label: l("King", "King"),
    size_m2: 28,
    view: l("Nile view", "Vista Nilo"),
    amenities: roomAmenities,
    price_per_night: 85,
    free_cancellation: true,
    breakfast_included: true,
    few_left: true,
    min_nights: 1,
    max_nights: 21,
    extra_bed_price: 20,
    price_list: seasons({ night: 85 }, 2000),
  },
  {
    id: 2,
    building_id: 1,
    name: l("Family Triple", "Tripla Family"),
    room_type: "triple",
    type_label: l("Triple room, family", "Camera tripla, famiglia"),
    description: l("Three beds and a sitting area, ideal for families visiting Luxor.", "Tre letti e un salottino, ideale per famiglie in visita a Luxor."),
    image: "/images/room-family.webp",
    gallery: ["/images/room-family.webp", "/images/room-deluxe.webp", "/images/luxor.webp"],
    max_adults: 3,
    max_children: 1,
    max_total: 4,
    beds: 3,
    bed_label: l("3 beds", "3 letti"),
    size_m2: 34,
    view: l("Nile view", "Vista Nilo"),
    amenities: roomAmenities,
    price_per_night: 110,
    free_cancellation: true,
    breakfast_included: true,
    few_left: false,
    min_nights: 2,
    max_nights: 21,
    extra_bed_price: 20,
    price_list: seasons({ night: 110 }, 2100),
  },
  {
    id: 3,
    building_id: 1,
    name: l("Single Standard", "Singola Standard"),
    room_type: "single",
    type_label: l("Single room", "Camera singola"),
    description: l("A quiet single room facing the garden.", "Una tranquilla camera singola sul giardino."),
    image: "/images/room-deluxe.webp",
    gallery: ["/images/room-deluxe.webp", "/images/luxor.webp"],
    max_adults: 1,
    max_children: 0,
    max_total: 1,
    beds: 1,
    bed_label: l("Single", "Singolo"),
    size_m2: 16,
    view: l("Garden view", "Vista giardino"),
    amenities: roomAmenities,
    price_per_night: 50,
    free_cancellation: true,
    breakfast_included: true,
    few_left: true,
    min_nights: 1,
    max_nights: 21,
    extra_bed_price: 0,
    price_list: seasons({ night: 50 }, 2200),
  },
  {
    id: 4,
    building_id: 2,
    name: l("Sea View Double", "Doppia Vista Mare"),
    room_type: "double",
    type_label: l("Double room, king or twin", "Camera doppia, king o twin"),
    description: l("Balcony over the Red Sea and direct beach access.", "Balcone sul Mar Rosso e accesso diretto alla spiaggia."),
    image: "/images/hurghada.webp",
    gallery: ["/images/hurghada.webp", "/images/sharm.webp", "/images/room-deluxe.webp"],
    max_adults: 2,
    max_children: 1,
    max_total: 3,
    beds: 1,
    bed_label: l("King", "King"),
    size_m2: 30,
    view: l("Sea view", "Vista mare"),
    amenities: [l("Wi-Fi", "Wi-Fi"), l("Breakfast", "Colazione"), l("Sea view", "Vista mare")],
    price_per_night: 70,
    free_cancellation: true,
    breakfast_included: true,
    few_left: false,
    min_nights: 1,
    max_nights: 21,
    extra_bed_price: 18,
    price_list: seasons({ night: 70 }, 2300),
  },
];

export const MOCK_REVIEWS: GoogleReview[] = [
  { id: "g1", author_name: "Giulia R.", author_country: "Italia", rating: 5, text: "Guida bravissima e organizzazione perfetta, ci siamo sentiti al sicuro per tutto il viaggio.", language: "it", relative_time: "2 settimane fa", published_at: "2026-10-30" },
  { id: "g2", author_name: "Mark T.", author_country: "United Kingdom", rating: 5, text: "Booking was simple and paying a deposit made it easy to plan.", language: "en", relative_time: "2 weeks ago", published_at: "2026-10-29" },
  { id: "g3", author_name: "Sophie L.", author_country: "France", rating: 5, text: "The Nile cruise was beautifully organised, and free cancellation gave us real peace of mind.", language: "en", relative_time: "Sep 2026", published_at: "2026-09-14" },
  { id: "g4", author_name: "Luca M.", author_country: "Italia", rating: 5, text: "Snorkeling a Ras Mohammed indimenticabile, barca pulita e staff gentilissimo.", language: "it", relative_time: "1 mese fa", published_at: "2026-10-05" },
  { id: "g5", author_name: "Hannah K.", author_country: "Germany", rating: 4, text: "Great buggy safari at sunset. Pick-up was a few minutes late but the guide was excellent.", language: "en", relative_time: "1 month ago", published_at: "2026-10-02" },
  { id: "g6", author_name: "Ahmed S.", author_country: "Egypt", rating: 5, text: "Professional team and fair prices. The Pyramids tour with lunch by the Nile was perfect.", language: "en", relative_time: "2 months ago", published_at: "2026-09-08" },
];

export const MOCK_GOOGLE = { rating: 4.8, total: 1284 };

export const MOCK_TRANSFER_HUBS = [
  { code: "CAI", name: "Cairo Airport (CAI)" },
  { code: "HRG", name: "Hurghada Airport (HRG)" },
  { code: "SSH", name: "Sharm El Sheikh Airport (SSH)" },
  { code: "LXR", name: "Luxor Airport (LXR)" },
  { code: "ASW", name: "Aswan Airport (ASW)" },
];

export const MOCK_VEHICLES = [
  { code: "sedan" as const, seats: 3, base: 20, per_km: 0.45 },
  { code: "minivan" as const, seats: 7, base: 32, per_km: 0.6 },
  { code: "van" as const, seats: 14, base: 48, per_km: 0.8 },
];

export const PRIVATE_LEAD_HOURS = 24;
export { CHILD_BANDS, STANDARD_FAQ, STANDARD_POLICIES };
