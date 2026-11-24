/**
 * OFFLINE FALLBACK ONLY: the website reads this content from the API (lib/api/content.ts).
 * This copy is served only when the API is unreachable (see lib/content/fallback.ts).
 */
import type { Locale } from "../api/types";

export interface BlogPost {
  slug: string;
  image: string;
  published: string;
  readMinutes: number;
  author: string;
  related: { label: Record<Locale, string>; href: string }[];
  locales: Record<Locale, { title: string; excerpt: string; tags: string[]; sections: { id: string; heading: string; paragraphs: string[] }[] }>;
}

export const POSTS: BlogPost[] = [
  {
    slug: "first-trip-to-egypt-8-days",
    image: "/images/cairo.webp",
    published: "2026-10-20",
    readMinutes: 7,
    author: "Sara Mahmoud",
    related: [
      { label: { en: "Cairo and the Pyramids", it: "Il Cairo e le Piramidi" }, href: "/destinations/cairo" },
      { label: { en: "Nile trips", it: "Crociere sul Nilo" }, href: "/nile" },
    ],
    locales: {
      en: {
        title: "Your first trip to Egypt: a thoughtful 8-day plan",
        excerpt: "Cairo, Luxor and Aswan with enough room to breathe: the route we suggest to friends visiting for the first time.",
        tags: ["Itinerary", "First time"],
        sections: [
          {
            id: "route",
            heading: "Start with a route, not a checklist",
            paragraphs: [
              "Egypt rewards slow travel. Eight days is enough to see the Pyramids, the temples of Luxor and the river at Aswan without spending every afternoon in a minibus. The secret is to fly the long distances and let the Nile do the rest.",
              "Our suggested route: three nights in Cairo, two nights in Luxor, then a short cruise or two nights in Aswan. Fly back to Cairo for your flight home, or add a few days on the Red Sea.",
            ],
          },
          {
            id: "cairo",
            heading: "Days 1–3: Cairo and Giza",
            paragraphs: [
              "Arrive, rest, and keep the first evening simple. On day two, visit the Pyramids and the Sphinx early, before the heat and the crowds, then spend the afternoon at the Grand Egyptian Museum. Day three is for Islamic Cairo and the Khan el-Khalili bazaar.",
            ],
          },
          {
            id: "luxor",
            heading: "Days 4–5: Luxor's two banks",
            paragraphs: [
              "Take the morning flight to Luxor. Karnak and Luxor temple sit on the East Bank; see Luxor temple at dusk when it is lit. Cross the river on day five for the Valley of the Kings and Hatshepsut's temple. If you can, start with a sunrise balloon flight.",
            ],
          },
          {
            id: "aswan",
            heading: "Days 6–8: the river rhythm of Aswan",
            paragraphs: [
              "Aswan is slower and greener. Sail a felucca at sunset, visit Philae temple by boat and spend an afternoon in a Nubian village. Abu Simbel is a long but unforgettable early-morning excursion.",
            ],
          },
          {
            id: "tips",
            heading: "What to book before you go",
            paragraphs: [
              "Book internal flights and the cruise first: they sell out in winter. Day trips can be booked a few days ahead, and most have free cancellation up to 24 hours before. Pack light layers, a hat and comfortable shoes for uneven temple floors.",
            ],
          },
        ],
      },
      it: {
        title: "Il primo viaggio in Egitto: un itinerario di 8 giorni",
        excerpt: "Cairo, Luxor e Assuan con il tempo di respirare: il percorso che consigliamo agli amici che partono per la prima volta.",
        tags: ["Itinerario", "Prima volta"],
        sections: [
          {
            id: "route",
            heading: "Parti da un itinerario, non da una lista",
            paragraphs: [
              "L'Egitto premia chi viaggia con calma. Otto giorni bastano per le Piramidi, i templi di Luxor e il fiume ad Assuan senza passare ogni pomeriggio in minibus. Il segreto è volare sulle lunghe distanze e lasciare che il Nilo faccia il resto.",
              "Il nostro itinerario: tre notti al Cairo, due a Luxor, poi una breve crociera o due notti ad Assuan. Rientra in volo al Cairo per il ritorno, oppure aggiungi qualche giorno sul Mar Rosso.",
            ],
          },
          {
            id: "cairo",
            heading: "Giorni 1–3: Cairo e Giza",
            paragraphs: [
              "Arriva, riposa e tieni semplice la prima sera. Il secondo giorno visita Piramidi e Sfinge di prima mattina, prima del caldo e della folla, poi il Grand Egyptian Museum nel pomeriggio. Il terzo giorno è per il Cairo islamico e il bazar di Khan el-Khalili.",
            ],
          },
          {
            id: "luxor",
            heading: "Giorni 4–5: le due rive di Luxor",
            paragraphs: [
              "Prendi il volo del mattino per Luxor. Karnak e il tempio di Luxor sono sulla riva est; visita il tempio di Luxor al tramonto, quando è illuminato. Il quinto giorno attraversa il fiume per la Valle dei Re e il tempio di Hatshepsut. Se puoi, inizia con il volo in mongolfiera all'alba.",
            ],
          },
          {
            id: "aswan",
            heading: "Giorni 6–8: il ritmo del fiume ad Assuan",
            paragraphs: [
              "Assuan è più lenta e più verde. Naviga in feluca al tramonto, raggiungi in barca il tempio di Philae e trascorri un pomeriggio in un villaggio nubiano. Abu Simbel è un'escursione lunga all'alba, ma indimenticabile.",
            ],
          },
          {
            id: "tips",
            heading: "Cosa prenotare prima di partire",
            paragraphs: [
              "Prenota per primi i voli interni e la crociera: in inverno si esauriscono. Le escursioni si possono prenotare pochi giorni prima e molte hanno cancellazione gratuita fino a 24 ore prima. Porta strati leggeri, un cappello e scarpe comode per i pavimenti irregolari dei templi.",
            ],
          },
        ],
      },
    },
  },
  {
    slug: "choosing-a-nile-cruise",
    image: "/images/nile-cruise.webp",
    published: "2026-09-28",
    readMinutes: 5,
    author: "Marco Bianchi",
    related: [
      { label: { en: "Nile trips", it: "Crociere sul Nilo" }, href: "/nile" },
      { label: { en: "Discover Luxor", it: "Scopri Luxor" }, href: "/destinations/luxor" },
    ],
    locales: {
      en: {
        title: "Choosing a Nile cruise: what to ask before you book",
        excerpt: "Three, four or seven nights? Upstream or downstream? The questions that make the difference between a good cruise and a great one.",
        tags: ["Nile", "Guide"],
        sections: [
          {
            id: "length",
            heading: "How many nights?",
            paragraphs: [
              "Four nights from Luxor to Aswan (or three in the other direction) cover the essential temples: Karnak, the West Bank, Edfu, Kom Ombo and Philae. Seven nights add a slower pace and the return journey, ideal if the cruise is the heart of your holiday.",
            ],
          },
          {
            id: "boat",
            heading: "The boat matters more than the stars",
            paragraphs: [
              "Ask about the size of the sun deck, whether there is a pool and how many cabins the boat has. Smaller boats feel calmer and dock closer to the temples. Cabins are booked by type; the deck is assigned on board.",
            ],
          },
          {
            id: "included",
            heading: "What is included",
            paragraphs: [
              "Most cruises are full board with guided visits. Check whether entrance fees, drinks and the Abu Simbel excursion are included, and whether the price is per person in a shared cabin.",
            ],
          },
          {
            id: "season",
            heading: "When to go",
            paragraphs: [
              "October to April is the most comfortable season. Summer cruises are cheaper and quieter, with visits early in the morning and late in the afternoon to avoid the heat.",
            ],
          },
        ],
      },
      it: {
        title: "Scegliere una crociera sul Nilo: cosa chiedere prima di prenotare",
        excerpt: "Tre, quattro o sette notti? Controcorrente o a favore? Le domande che fanno la differenza tra una buona crociera e una crociera speciale.",
        tags: ["Nilo", "Guida"],
        sections: [
          {
            id: "length",
            heading: "Quante notti?",
            paragraphs: [
              "Quattro notti da Luxor ad Assuan (o tre nel senso opposto) coprono i templi essenziali: Karnak, la riva ovest, Edfu, Kom Ombo e Philae. Sette notti aggiungono un ritmo più lento e il viaggio di ritorno, ideali se la crociera è il cuore della vacanza.",
            ],
          },
          {
            id: "boat",
            heading: "La nave conta più delle stelle",
            paragraphs: [
              "Chiedi quanto è grande il ponte sole, se c'è una piscina e quante cabine ha la nave. Le navi più piccole sono più tranquille e attraccano più vicino ai templi. Le cabine si prenotano per tipologia; il ponte viene assegnato a bordo.",
            ],
          },
          {
            id: "included",
            heading: "Cosa è incluso",
            paragraphs: [
              "La maggior parte delle crociere è in pensione completa con visite guidate. Verifica se ingressi, bevande ed escursione ad Abu Simbel sono inclusi, e se il prezzo è a persona in cabina condivisa.",
            ],
          },
          {
            id: "season",
            heading: "Quando partire",
            paragraphs: [
              "Da ottobre ad aprile è la stagione più piacevole. Le crociere estive costano meno e sono più tranquille, con visite al mattino presto e nel tardo pomeriggio per evitare il caldo.",
            ],
          },
        ],
      },
    },
  },
];

export const getPost = (slug: string) => POSTS.find((p) => p.slug === slug) ?? null;
