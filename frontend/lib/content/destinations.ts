/**
 * Editorial content for destination pages (best time, reasons to visit, FAQ).
 * The API provides name, description, trips and buildings; this adds the
 * travel-guide layer until the CMS has these fields.
 */
import type { Locale } from "../api/types";

export interface DestinationGuide {
  bestTime: string;
  why: { title: string; text: string }[];
  faq: { question: string; answer: string }[];
}

type Guides = Record<string, Record<Locale, DestinationGuide>>;

const GUIDES: Guides = {
  "sharm-el-sheikh": {
    en: {
      bestTime: "Mar–May, Oct–Nov",
      why: [
        { title: "Ras Mohammed reefs", text: "Some of the Red Sea's healthiest coral, a short boat ride from Naama Bay." },
        { title: "Sinai desert at sunset", text: "Buggy and quad safaris end with tea in a Bedouin camp under the stars." },
        { title: "An easy base", text: "Direct flights, calm beaches and day trips to Mount Sinai and Dahab." },
      ],
      faq: [
        { question: "Is Sharm good for non-swimmers?", answer: "Yes. Many reefs start in shallow water, and glass-bottom boats and semi-submarines show the coral without getting wet." },
        { question: "Can I visit Cairo from Sharm?", answer: "Yes, with a one-day flight excursion or, better, an overnight trip to see the Pyramids and the museum without rushing." },
        { question: "Do I need a visa?", answer: "Many nationalities can get a free Sinai-only stamp on arrival. Add an Egypt visa if you plan to leave South Sinai." },
      ],
    },
    it: {
      bestTime: "mar–mag, ott–nov",
      why: [
        { title: "La barriera di Ras Mohammed", text: "Alcuni dei coralli più belli del Mar Rosso, a poca distanza in barca da Naama Bay." },
        { title: "Il deserto del Sinai al tramonto", text: "Safari in buggy e quad che finiscono con un tè in un campo beduino sotto le stelle." },
        { title: "Una base comoda", text: "Voli diretti, spiagge tranquille ed escursioni al Monte Sinai e a Dahab." },
      ],
      faq: [
        { question: "Sharm va bene per chi non nuota?", answer: "Sì. Molte barriere iniziano in acque basse, e le barche con fondo di vetro e i semi-sommergibili mostrano i coralli senza bagnarsi." },
        { question: "Si può visitare il Cairo da Sharm?", answer: "Sì, con un'escursione in giornata in aereo o, meglio, con una notte al Cairo per vedere Piramidi e museo senza fretta." },
        { question: "Serve il visto?", answer: "Molte nazionalità ottengono all'arrivo un timbro gratuito valido solo per il Sinai. Serve il visto egiziano se lasci il Sinai del Sud." },
      ],
    },
  },
  luxor: {
    en: {
      bestTime: "Oct–Apr",
      why: [
        { title: "Karnak and the East Bank temples", text: "Walk the hypostyle hall of Karnak, then see Luxor temple lit up at night." },
        { title: "The Valley of the Kings at dawn", text: "Royal tombs in the cool morning, before or after a balloon flight over the West Bank." },
        { title: "Sunset sails on the Nile", text: "Felucca rides and dinner cruises connect the city with its river." },
      ],
      faq: [
        { question: "How many days do I need in Luxor?", answer: "Two full days cover Karnak, Luxor temple and the Valley of the Kings. Add a third day for the balloon flight and Dendera or Abydos." },
        { question: "What is the best time to visit?", answer: "October to April. In summer, plan visits at sunrise and late afternoon." },
        { question: "Can I combine Luxor with a Nile cruise?", answer: "Yes. Most cruises start or end in Luxor and sail to Aswan in three or four nights." },
        { question: "Are hotel transfers included in trips?", answer: "Pick-up and drop-off from Luxor hotels are included in most day trips; the itinerary on each trip says so." },
      ],
    },
    it: {
      bestTime: "ott–apr",
      why: [
        { title: "Karnak e i templi della riva est", text: "Percorri la sala ipostila di Karnak, poi ammira il tempio di Luxor illuminato la sera." },
        { title: "La Valle dei Re all'alba", text: "Le tombe reali nel fresco del mattino, prima o dopo un volo in mongolfiera sulla riva ovest." },
        { title: "Tramonti in barca sul Nilo", text: "Gite in feluca e cene in crociera uniscono la città al suo fiume." },
      ],
      faq: [
        { question: "Quanti giorni servono a Luxor?", answer: "Due giorni pieni per Karnak, il tempio di Luxor e la Valle dei Re. Aggiungi un terzo giorno per la mongolfiera e Dendera o Abido." },
        { question: "Qual è il periodo migliore?", answer: "Da ottobre ad aprile. In estate organizza le visite all'alba e nel tardo pomeriggio." },
        { question: "Posso unire Luxor a una crociera sul Nilo?", answer: "Sì. La maggior parte delle crociere parte o arriva a Luxor e naviga verso Assuan in tre o quattro notti." },
        { question: "I transfer dall'hotel sono inclusi?", answer: "Prelievo e rientro dagli hotel di Luxor sono inclusi nella maggior parte delle escursioni; lo indica il programma di ognuna." },
      ],
    },
  },
  aswan: {
    en: {
      bestTime: "Nov–Feb",
      why: [
        { title: "Philae by boat", text: "The temple of Isis on its island, reached by motorboat across the reservoir." },
        { title: "Nubian villages", text: "Colourful houses, spice markets and a warm welcome on the West Bank." },
        { title: "Gateway to Abu Simbel", text: "Ramses II's great temples, a sunrise excursion from Aswan." },
      ],
      faq: [
        { question: "Is Abu Simbel worth the early start?", answer: "Yes. The road trip leaves around 4 a.m. and you are back by early afternoon, or you can fly." },
        { question: "How hot is Aswan?", answer: "It is the hottest city in Egypt. Winter days are warm and dry; summer is best for experienced travellers." },
      ],
    },
    it: {
      bestTime: "nov–feb",
      why: [
        { title: "Philae in barca", text: "Il tempio di Iside sulla sua isola, raggiunto in motoscafo attraverso il bacino." },
        { title: "I villaggi nubiani", text: "Case colorate, mercati di spezie e un'accoglienza calorosa sulla riva ovest." },
        { title: "La porta di Abu Simbel", text: "I grandi templi di Ramses II, un'escursione all'alba da Assuan." },
      ],
      faq: [
        { question: "Abu Simbel vale la sveglia all'alba?", answer: "Sì. Si parte verso le 4 del mattino e si rientra nel primo pomeriggio, oppure si può volare." },
        { question: "Quanto fa caldo ad Assuan?", answer: "È la città più calda d'Egitto. In inverno le giornate sono tiepide e secche; l'estate è per viaggiatori esperti." },
      ],
    },
  },
  cairo: {
    en: {
      bestTime: "Oct–Apr",
      why: [
        { title: "The Pyramids of Giza", text: "The last standing wonder of the ancient world, best seen early in the morning." },
        { title: "The Grand Egyptian Museum", text: "Tutankhamun's treasures displayed together for the first time." },
        { title: "Islamic Cairo", text: "Mosques, madrasas and the Khan el-Khalili bazaar in the old city." },
      ],
      faq: [
        { question: "How many days for Cairo?", answer: "Two full days: one for Giza and the museum, one for the old city." },
        { question: "Is it easy to get around?", answer: "Traffic is heavy; a private driver or a guided trip saves a lot of time." },
      ],
    },
    it: {
      bestTime: "ott–apr",
      why: [
        { title: "Le Piramidi di Giza", text: "L'ultima meraviglia del mondo antico ancora in piedi, da vedere di prima mattina." },
        { title: "Il Grand Egyptian Museum", text: "I tesori di Tutankhamon esposti insieme per la prima volta." },
        { title: "Il Cairo islamico", text: "Moschee, madrase e il bazar di Khan el-Khalili nella città vecchia." },
      ],
      faq: [
        { question: "Quanti giorni per il Cairo?", answer: "Due giorni pieni: uno per Giza e il museo, uno per la città vecchia." },
        { question: "È facile spostarsi?", answer: "Il traffico è intenso; un autista privato o un'escursione guidata fanno risparmiare molto tempo." },
      ],
    },
  },
  hurghada: {
    en: {
      bestTime: "Mar–May, Sep–Nov",
      why: [
        { title: "Giftun Island", text: "White sand and turquoise water for snorkelling, a short boat ride away." },
        { title: "Family beaches", text: "Gentle shallow water and resorts with kids' clubs." },
        { title: "Close to Luxor", text: "Luxor's temples are a long day trip away by road." },
      ],
      faq: [{ question: "Hurghada or Sharm?", answer: "Hurghada is better for family beaches and Luxor day trips; Sharm for coral reefs and the Sinai desert." }],
    },
    it: {
      bestTime: "mar–mag, set–nov",
      why: [
        { title: "L'isola di Giftun", text: "Sabbia bianca e acqua turchese per lo snorkeling, a poca distanza in barca." },
        { title: "Spiagge per famiglie", text: "Acque basse e tranquille e resort con mini club." },
        { title: "Vicina a Luxor", text: "I templi di Luxor sono raggiungibili con una lunga escursione in giornata." },
      ],
      faq: [{ question: "Meglio Hurghada o Sharm?", answer: "Hurghada è migliore per spiagge per famiglie ed escursioni a Luxor; Sharm per le barriere coralline e il deserto del Sinai." }],
    },
  },
};

const FALLBACK: Record<Locale, DestinationGuide> = {
  en: {
    bestTime: "Oct–Apr",
    why: [
      { title: "Local guides", text: "Every experience is led by licensed guides who live here." },
      { title: "Flexible plans", text: "Private or group, with a deposit to reserve and the balance later." },
      { title: "Easy connections", text: "Combine it with other destinations in one package, transfers included." },
    ],
    faq: [{ question: "When is the best time to visit?", answer: "October to April is the most comfortable season across most of Egypt." }],
  },
  it: {
    bestTime: "ott–apr",
    why: [
      { title: "Guide locali", text: "Ogni esperienza è guidata da guide autorizzate che vivono qui." },
      { title: "Programmi flessibili", text: "Privato o di gruppo, con un acconto per prenotare e il saldo più tardi." },
      { title: "Collegamenti semplici", text: "Uniscila ad altre destinazioni in un pacchetto, transfer inclusi." },
    ],
    faq: [{ question: "Qual è il periodo migliore per visitarla?", answer: "Da ottobre ad aprile è la stagione più piacevole in quasi tutto l'Egitto." }],
  },
};

export function destinationGuide(slug: string, locale: Locale): DestinationGuide {
  return GUIDES[slug]?.[locale] ?? FALLBACK[locale];
}
