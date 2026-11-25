/**
 * OFFLINE FALLBACK ONLY: the website reads this content from the API (lib/api/content.ts).
 * This copy is served only when the API is unreachable (see lib/content/fallback.ts).
 */
/**
 * Static pages (About + legal), EN and IT. Plain TS content, no MDX.
 * Legal texts describe how the platform works (SRS 10 payments, 12.4 cancellation);
 * have them reviewed by counsel before going live.
 */
import type { Locale } from "../api/types";

export interface DocSection {
  id: string;
  heading: string;
  paragraphs: string[];
  list?: string[];
}

export interface Doc {
  title: string;
  description: string;
  intro: string;
  updated: string;
  sections: DocSection[];
}

export type StaticSlug = "about" | "booking-terms" | "privacy" | "cancellation-policy" | "cookie-policy";

export const LEGAL_LINKS: { slug: StaticSlug; key: "terms" | "privacy" | "cancellation" | "cookiePolicy" }[] = [
  { slug: "booking-terms", key: "terms" },
  { slug: "cancellation-policy", key: "cancellation" },
  { slug: "privacy", key: "privacy" },
  { slug: "cookie-policy", key: "cookiePolicy" },
];

const UPDATED = "2026-11-01";

export const PAGES: Record<StaticSlug, Record<Locale, Doc>> = {
  about: {
    en: {
      title: "Egypt is more than a place to visit",
      description: "Sharm Station Viaggi is a small Egyptian travel team planning day trips, Nile cruises, packages, hotels and transfers with local guides and flexible payment.",
      intro:
        "We are a team of Egyptian guides, drivers and planners based in Naama Bay, Sharm El Sheikh. Since our first desert safari we have believed that a good trip is built on small, honest details: a clear price, a driver who is on time, and a guide who loves the place as much as you will.",
      updated: UPDATED,
      sections: [
        {
          id: "story",
          heading: "Thoughtful journeys, from the first question",
          paragraphs: [
            "Sharm Station Viaggi started by organising excursions for Italian and British travellers staying on the Red Sea. Guests kept asking for more: a night in Luxor, a few days on the Nile, a transfer to Cairo airport that would not leave them waiting. Today we plan complete journeys across Egypt, and we still answer every message ourselves.",
            "Every trip on this website is run by people we know personally: boat captains in Ras Mohammed, Bedouin families in the Sinai, Egyptologists in Luxor and Aswan, and the crews of the cruise ships we work with.",
          ],
        },
        {
          id: "promise",
          heading: "A good journey is in the details",
          paragraphs: ["We keep a few promises on every booking, whether it is a two-hour snorkelling trip or a twelve-day package."],
          list: [
            "Clarity before checkout: the full price, what is included and the cancellation terms are shown before you pay.",
            "Rooms to travel your way: private or group, one room or several, with children's prices calculated by age.",
            "Respect for the place: small groups, licensed guides and operators who follow national park rules.",
            "A deposit to reserve: you pay a deposit online or by bank transfer, and the balance later.",
          ],
        },
        {
          id: "people",
          heading: "The people behind your plans",
          paragraphs: [
            "Our travel planners speak English, Italian and Arabic. They check availability with hotels and boats, build multi-stop routes and stay reachable on WhatsApp throughout your stay.",
            "Our guest care team looks after changes and special requests, from a vegetarian lunch on a boat to a wheelchair-friendly vehicle. On the ground, our coordinators meet you at the airport and make sure every pick-up happens as planned.",
          ],
        },
        {
          id: "partners",
          heading: "Working with travel partners",
          paragraphs: [
            "Agencies, hotels and tour operators can book every service on this website at partner prices, with their own panel for bookings, statements and payments. If you sell Egypt to your clients, we would be glad to work with you.",
          ],
        },
      ],
    },
    it: {
      title: "L'Egitto è più di un luogo da visitare",
      description: "Sharm Station Viaggi è un piccolo team egiziano che organizza escursioni, crociere sul Nilo, pacchetti, hotel e transfer con guide locali e pagamenti flessibili.",
      intro:
        "Siamo un team di guide, autisti e organizzatori egiziani con sede a Naama Bay, Sharm El Sheikh. Dal nostro primo safari nel deserto crediamo che un buon viaggio si costruisca con piccoli dettagli onesti: un prezzo chiaro, un autista puntuale e una guida che ama il luogo quanto lo amerai tu.",
      updated: UPDATED,
      sections: [
        {
          id: "story",
          heading: "Viaggi pensati, fin dalla prima domanda",
          paragraphs: [
            "Sharm Station Viaggi è nata organizzando escursioni per viaggiatori italiani e britannici in vacanza sul Mar Rosso. Gli ospiti chiedevano sempre di più: una notte a Luxor, qualche giorno sul Nilo, un transfer per l'aeroporto del Cairo senza attese. Oggi organizziamo viaggi completi in tutto l'Egitto e rispondiamo ancora personalmente a ogni messaggio.",
            "Ogni esperienza su questo sito è gestita da persone che conosciamo di persona: capitani di barca a Ras Mohammed, famiglie beduine nel Sinai, egittologi a Luxor e Assuan e gli equipaggi delle navi da crociera con cui lavoriamo.",
          ],
        },
        {
          id: "promise",
          heading: "Un buon viaggio sta nei dettagli",
          paragraphs: ["Manteniamo alcune promesse su ogni prenotazione, che sia uno snorkeling di due ore o un pacchetto di dodici giorni."],
          list: [
            "Chiarezza prima del pagamento: prezzo completo, servizi inclusi e condizioni di cancellazione sono visibili prima di pagare.",
            "Camere a modo tuo: privato o di gruppo, una camera o più, con prezzi bambini calcolati in base all'età.",
            "Rispetto per i luoghi: gruppi piccoli, guide autorizzate e operatori che rispettano le regole dei parchi nazionali.",
            "Un acconto per prenotare: paghi un acconto online o con bonifico, e il saldo più avanti.",
          ],
        },
        {
          id: "people",
          heading: "Le persone dietro i tuoi programmi",
          paragraphs: [
            "I nostri consulenti di viaggio parlano italiano, inglese e arabo. Verificano la disponibilità con hotel e barche, costruiscono itinerari a più tappe e restano raggiungibili su WhatsApp per tutto il soggiorno.",
            "Il team di assistenza gestisce modifiche e richieste speciali, da un pranzo vegetariano in barca a un veicolo accessibile. Sul posto, i nostri coordinatori ti accolgono in aeroporto e controllano che ogni prelievo avvenga come previsto.",
          ],
        },
        {
          id: "partners",
          heading: "Collaborare con i partner",
          paragraphs: [
            "Agenzie, hotel e tour operator possono prenotare tutti i servizi del sito a prezzi partner, con un'area dedicata per prenotazioni, estratti conto e pagamenti. Se vendi l'Egitto ai tuoi clienti, saremo felici di lavorare con te.",
          ],
        },
      ],
    },
  },

  "booking-terms": {
    en: {
      title: "Booking terms",
      description: "The terms that apply when you book a trip, Nile cruise, package, transfer or room with Sharm Station Viaggi.",
      intro: "These terms apply to every booking made on this website, by phone or on WhatsApp. Please read them together with the cancellation policy and the privacy notice. By completing a booking you accept them.",
      updated: UPDATED,
      sections: [
        {
          id: "who",
          heading: "1. Who we are",
          paragraphs: ["Sharm Station Viaggi (\"we\", \"us\") is a travel organiser based in Naama Bay, Sharm El Sheikh, Egypt. We sell excursions, Nile cruises, multi-day packages, transfers and accommodation operated by us or by local suppliers we select."],
        },
        {
          id: "booking",
          heading: "2. Making a booking",
          paragraphs: [
            "A booking request is created when you complete checkout and receive a booking reference. Its status is \"Awaiting payment\" until we receive the deposit. Accommodation may be held for a limited time shown at checkout; trips and packages are not held.",
            "The person who books (the lead traveller, or the travel partner booking for a client) must be at least 18 years old and is responsible for the accuracy of the traveller details, including children's ages, which determine the price.",
          ],
        },
        {
          id: "prices",
          heading: "3. Prices",
          paragraphs: [
            "Prices are calculated in euro for the travel date and season you select and include the services listed under \"Included\" on each page. Prices in other currencies are shown for convenience; the exchange rate is fixed at the moment of booking.",
            "Prices for private trips depend on the number of travellers; group trips are priced per person; some trips are priced per vehicle or unit (for example per buggy). Room prices are per night; city or tourist taxes are shown as included or extra on each room.",
          ],
        },
        {
          id: "payment",
          heading: "4. Deposit and balance",
          paragraphs: [
            "To confirm a booking you pay the deposit shown at checkout (usually 30% of the total) online by card or wallet, or by bank transfer with proof of payment. The booking becomes \"Confirmed\" when the deposit is received or the proof is approved, and you receive a voucher by email.",
            "The balance is due before the service date, in cash on the day or by any accepted method, as shown on your voucher. A payment method may carry a small surcharge, which is always displayed before you pay.",
          ],
        },
        {
          id: "changes",
          heading: "5. Changes by you",
          paragraphs: ["You can ask to change the date, the number of travellers or the rooms of a booking. We will recalculate the price for the new details and confirm the change if availability allows. Changes made inside the cancellation window may be treated as a cancellation."],
        },
        {
          id: "changes-us",
          heading: "6. Changes by us",
          paragraphs: ["Weather, sea conditions, national park closures or official instructions can require a change of itinerary, time or vessel. We will offer an equivalent alternative or a full refund of what you paid for the affected service."],
        },
        {
          id: "conduct",
          heading: "7. On the day",
          paragraphs: ["Please be ready at the pick-up point at the time on your voucher. Guides and captains may refuse participation for safety reasons, for example to travellers under the influence of alcohol. Follow the safety briefings and the rules of protected areas."],
        },
        {
          id: "liability",
          heading: "8. Liability and insurance",
          paragraphs: ["We choose our suppliers with care and are responsible for the services we sell, within the limits of applicable law. We strongly recommend travel insurance covering medical costs and cancellation. We are not responsible for losses caused by the traveller, by third parties unrelated to the services, or by unforeseeable events beyond our control."],
        },
        {
          id: "complaints",
          heading: "9. Complaints and law",
          paragraphs: ["If something is not right, tell your guide or message us immediately so we can fix it on the spot. Written complaints can be sent to our email within 28 days of the service. These terms are governed by Egyptian law, without prejudice to mandatory consumer rights in your country of residence."],
        },
      ],
    },
    it: {
      title: "Condizioni di prenotazione",
      description: "Le condizioni che si applicano quando prenoti un'escursione, una crociera sul Nilo, un pacchetto, un transfer o una camera con Sharm Station Viaggi.",
      intro: "Queste condizioni si applicano a ogni prenotazione effettuata sul sito, per telefono o su WhatsApp. Leggile insieme alla politica di cancellazione e all'informativa privacy. Completando una prenotazione le accetti.",
      updated: UPDATED,
      sections: [
        {
          id: "who",
          heading: "1. Chi siamo",
          paragraphs: ["Sharm Station Viaggi (\"noi\") è un organizzatore di viaggi con sede a Naama Bay, Sharm El Sheikh, Egitto. Vendiamo escursioni, crociere sul Nilo, pacchetti di più giorni, transfer e alloggi gestiti da noi o da fornitori locali selezionati."],
        },
        {
          id: "booking",
          heading: "2. Come si prenota",
          paragraphs: [
            "La richiesta di prenotazione nasce quando completi il checkout e ricevi un codice di prenotazione. Lo stato resta \"In attesa di pagamento\" finché non riceviamo l'acconto. Gli alloggi possono essere bloccati per un tempo limitato indicato al checkout; escursioni e pacchetti non vengono bloccati.",
            "Chi prenota (il viaggiatore principale, o il partner che prenota per un cliente) deve avere almeno 18 anni ed è responsabile della correttezza dei dati dei viaggiatori, comprese le età dei bambini, che determinano il prezzo.",
          ],
        },
        {
          id: "prices",
          heading: "3. Prezzi",
          paragraphs: [
            "I prezzi sono calcolati in euro per la data e la stagione scelte e comprendono i servizi indicati come \"Incluso\" su ogni pagina. I prezzi in altre valute sono indicativi; il tasso di cambio è fissato al momento della prenotazione.",
            "Le escursioni private hanno prezzi che dipendono dal numero di viaggiatori; quelle di gruppo sono a persona; alcune escursioni hanno un prezzo per veicolo o unità (ad esempio per buggy). Le camere hanno prezzi a notte; le tasse di soggiorno sono indicate come incluse o extra su ogni camera.",
          ],
        },
        {
          id: "payment",
          heading: "4. Acconto e saldo",
          paragraphs: [
            "Per confermare la prenotazione paghi l'acconto indicato al checkout (di solito il 30% del totale) online con carta o wallet, oppure con bonifico e caricamento della ricevuta. La prenotazione diventa \"Confermata\" quando riceviamo l'acconto o approviamo la ricevuta, e ricevi un voucher via email.",
            "Il saldo è dovuto prima della data del servizio, in contanti il giorno stesso o con uno dei metodi accettati, come indicato sul voucher. Alcuni metodi di pagamento possono prevedere una piccola commissione, sempre mostrata prima del pagamento.",
          ],
        },
        {
          id: "changes",
          heading: "5. Modifiche richieste da te",
          paragraphs: ["Puoi chiedere di cambiare data, numero di viaggiatori o camere. Ricalcoliamo il prezzo con i nuovi dati e confermiamo la modifica se c'è disponibilità. Le modifiche richieste all'interno del periodo di cancellazione possono essere trattate come una cancellazione."],
        },
        {
          id: "changes-us",
          heading: "6. Modifiche da parte nostra",
          paragraphs: ["Meteo, condizioni del mare, chiusure dei parchi nazionali o disposizioni ufficiali possono richiedere modifiche di itinerario, orario o imbarcazione. Ti offriremo un'alternativa equivalente o il rimborso completo di quanto pagato per il servizio interessato."],
        },
        {
          id: "conduct",
          heading: "7. Il giorno del servizio",
          paragraphs: ["Fatti trovare al punto di prelievo all'orario indicato sul voucher. Guide e capitani possono negare la partecipazione per motivi di sicurezza, ad esempio a chi è sotto l'effetto di alcol. Segui le istruzioni di sicurezza e le regole delle aree protette."],
        },
        {
          id: "liability",
          heading: "8. Responsabilità e assicurazione",
          paragraphs: ["Scegliamo con cura i nostri fornitori e rispondiamo dei servizi venduti nei limiti della legge applicabile. Consigliamo vivamente un'assicurazione di viaggio con copertura spese mediche e annullamento. Non rispondiamo di danni causati dal viaggiatore, da terzi estranei ai servizi o da eventi imprevedibili fuori dal nostro controllo."],
        },
        {
          id: "complaints",
          heading: "9. Reclami e legge applicabile",
          paragraphs: ["Se qualcosa non va, dillo subito alla guida o scrivici, così possiamo rimediare sul posto. I reclami scritti possono essere inviati alla nostra email entro 28 giorni dal servizio. Queste condizioni sono regolate dalla legge egiziana, fatti salvi i diritti inderogabili dei consumatori del tuo paese di residenza."],
        },
      ],
    },
  },

  "cancellation-policy": {
    en: {
      title: "Cancellation policy",
      description: "How cancellations and refunds work for trips, Nile cruises, packages, transfers and rooms booked with Sharm Station Viaggi.",
      intro: "Each trip, cruise, package, transfer and building has its own cancellation terms, shown on its page and on your voucher. This page explains how they work and what happens to your deposit.",
      updated: UPDATED,
      sections: [
        {
          id: "how",
          heading: "How cancellation windows work",
          paragraphs: [
            "A cancellation policy is a set of time bands counted back from the start of the service (the pick-up time for trips, the check-in date for rooms). The band you are in when you cancel decides the refund. A typical policy looks like this:",
          ],
          list: ["More than 7 days before: full refund of what you paid.", "Between 3 and 7 days before: 50% refund.", "Less than 3 days before, or no-show: no refund."],
        },
        {
          id: "free",
          heading: "Free cancellation",
          paragraphs: ["Products marked \"Free cancellation\" can be cancelled up to the time shown (for example 24 hours before departure) for a full refund of the deposit. The exact window is shown in the booking widget and on your voucher."],
        },
        {
          id: "how-to",
          heading: "How to cancel",
          paragraphs: [
            "Send us your booking reference by email or WhatsApp, or use the contact link on your booking page. Requesting a cancellation does not cancel the booking automatically: we confirm the cancellation and the refund amount in writing, usually the same day.",
          ],
        },
        {
          id: "refunds",
          heading: "Refunds",
          paragraphs: [
            "The suggested refund is calculated automatically from the policy and confirmed by our team. Refunds are paid to the original payment method within 14 days of confirmation. Bank charges and payment surcharges are not refundable. Refunds in a currency other than euro use the exchange rate fixed at booking.",
          ],
        },
        {
          id: "weather",
          heading: "Weather and official closures",
          paragraphs: ["When we cancel a trip because of weather, sea conditions or an official closure, you choose between another date, an equivalent alternative or a full refund."],
        },
        {
          id: "partners",
          heading: "Bookings made by travel partners",
          paragraphs: ["If a travel agency booked for you, please contact the agency: they will request the cancellation from us under the same policy."],
        },
      ],
    },
    it: {
      title: "Politica di cancellazione",
      description: "Come funzionano cancellazioni e rimborsi per escursioni, crociere sul Nilo, pacchetti, transfer e camere prenotati con Sharm Station Viaggi.",
      intro: "Ogni escursione, crociera, pacchetto, transfer e struttura ha le proprie condizioni di cancellazione, indicate sulla pagina e sul voucher. Qui spieghiamo come funzionano e cosa succede al tuo acconto.",
      updated: UPDATED,
      sections: [
        {
          id: "how",
          heading: "Come funzionano le fasce di cancellazione",
          paragraphs: [
            "Una politica di cancellazione è un insieme di fasce temporali contate a ritroso dall'inizio del servizio (l'orario di prelievo per le escursioni, la data di check-in per le camere). La fascia in cui cancelli determina il rimborso. Una politica tipica è questa:",
          ],
          list: ["Più di 7 giorni prima: rimborso completo di quanto pagato.", "Tra 3 e 7 giorni prima: rimborso del 50%.", "Meno di 3 giorni prima, o mancata presentazione: nessun rimborso."],
        },
        {
          id: "free",
          heading: "Cancellazione gratuita",
          paragraphs: ["I prodotti con \"Cancellazione gratuita\" si possono cancellare fino al momento indicato (ad esempio 24 ore prima della partenza) con rimborso completo dell'acconto. La finestra esatta è indicata nel modulo di prenotazione e sul voucher."],
        },
        {
          id: "how-to",
          heading: "Come cancellare",
          paragraphs: [
            "Inviaci il codice di prenotazione via email o WhatsApp, oppure usa il link di contatto nella pagina della prenotazione. La richiesta non cancella automaticamente la prenotazione: confermiamo per iscritto cancellazione e importo del rimborso, di solito in giornata.",
          ],
        },
        {
          id: "refunds",
          heading: "Rimborsi",
          paragraphs: [
            "Il rimborso suggerito è calcolato automaticamente in base alla politica e confermato dal nostro team. I rimborsi vengono effettuati sul metodo di pagamento originale entro 14 giorni dalla conferma. Spese bancarie e commissioni di pagamento non sono rimborsabili. I rimborsi in valute diverse dall'euro usano il cambio fissato alla prenotazione.",
          ],
        },
        {
          id: "weather",
          heading: "Meteo e chiusure ufficiali",
          paragraphs: ["Se cancelliamo un'escursione per meteo, condizioni del mare o chiusure ufficiali, puoi scegliere un'altra data, un'alternativa equivalente o il rimborso completo."],
        },
        {
          id: "partners",
          heading: "Prenotazioni fatte da agenzie partner",
          paragraphs: ["Se un'agenzia ha prenotato per te, contatta l'agenzia: richiederà la cancellazione a noi secondo la stessa politica."],
        },
      ],
    },
  },

  privacy: {
    en: {
      title: "Privacy notice",
      description: "How Sharm Station Viaggi collects, uses and protects your personal data when you browse, book or contact us.",
      intro: "This notice explains what personal data we collect, why we use it and the choices you have. We collect only what we need to plan and run your trip.",
      updated: UPDATED,
      sections: [
        {
          id: "controller",
          heading: "Who is responsible",
          paragraphs: ["Sharm Station Viaggi, Naama Bay, Sharm El Sheikh, Egypt, is responsible for your data. You can reach us for any privacy question at the email address shown on our contact page."],
        },
        {
          id: "data",
          heading: "What we collect",
          paragraphs: ["Depending on how you use the website, we process:"],
          list: [
            "Booking data: lead traveller name, email, phone, country, names and ages of travellers, pick-up hotel and special requests.",
            "Account data: name, email, password (stored hashed), language and currency preferences.",
            "Payment data: amounts, payment method and proofs of payment you upload. Card details are handled by the payment provider and never reach us.",
            "Messages you send us by form, email or WhatsApp.",
            "Technical data: IP address, browser and pages visited, used for security and, with consent, statistics.",
          ],
        },
        {
          id: "why",
          heading: "Why we use it",
          paragraphs: [
            "To create and manage your booking and send your voucher (contract); to keep accounting records (legal obligation); to prevent fraud and keep the website secure (legitimate interest); to send travel ideas by email only if you opted in (consent, which you can withdraw at any time).",
          ],
        },
        {
          id: "sharing",
          heading: "Who we share it with",
          paragraphs: ["We share the minimum necessary with the suppliers who deliver your services (hotels, boats, guides, drivers), with payment providers, and with IT providers who host our systems under contract. We never sell your data."],
        },
        {
          id: "retention",
          heading: "How long we keep it",
          paragraphs: ["Booking and accounting records are kept for the period required by tax law (up to 10 years). Proofs of payment are stored privately and deleted when no longer needed. Account data is kept until you ask us to delete the account."],
        },
        {
          id: "rights",
          heading: "Your rights",
          paragraphs: ["You can ask to access, correct, delete or export your data, object to its use or withdraw consent. Write to us from the email linked to your booking. If you live in the EU or UK you can also complain to your data protection authority."],
        },
      ],
    },
    it: {
      title: "Informativa privacy",
      description: "Come Sharm Station Viaggi raccoglie, usa e protegge i tuoi dati personali quando navighi, prenoti o ci contatti.",
      intro: "Questa informativa spiega quali dati personali raccogliamo, perché li usiamo e quali scelte hai. Raccogliamo solo ciò che serve per organizzare e gestire il tuo viaggio.",
      updated: UPDATED,
      sections: [
        {
          id: "controller",
          heading: "Titolare del trattamento",
          paragraphs: ["Il titolare è Sharm Station Viaggi, Naama Bay, Sharm El Sheikh, Egitto. Per qualsiasi domanda sulla privacy puoi scriverci all'indirizzo email indicato nella pagina contatti."],
        },
        {
          id: "data",
          heading: "Quali dati raccogliamo",
          paragraphs: ["A seconda di come usi il sito, trattiamo:"],
          list: [
            "Dati di prenotazione: nome, email, telefono e paese del viaggiatore principale, nomi ed età dei viaggiatori, hotel di prelievo e richieste speciali.",
            "Dati dell'account: nome, email, password (conservata in forma cifrata), preferenze di lingua e valuta.",
            "Dati di pagamento: importi, metodo di pagamento e ricevute caricate. I dati della carta sono gestiti dal fornitore del pagamento e non ci arrivano mai.",
            "Messaggi inviati tramite modulo, email o WhatsApp.",
            "Dati tecnici: indirizzo IP, browser e pagine visitate, usati per la sicurezza e, con il consenso, per statistiche.",
          ],
        },
        {
          id: "why",
          heading: "Perché li usiamo",
          paragraphs: [
            "Per creare e gestire la prenotazione e inviarti il voucher (contratto); per tenere la contabilità (obbligo di legge); per prevenire frodi e proteggere il sito (legittimo interesse); per inviarti idee di viaggio via email solo se lo hai richiesto (consenso, revocabile in qualsiasi momento).",
          ],
        },
        {
          id: "sharing",
          heading: "Con chi li condividiamo",
          paragraphs: ["Condividiamo il minimo necessario con i fornitori che erogano i servizi (hotel, barche, guide, autisti), con i fornitori di pagamento e con i fornitori informatici che ospitano i nostri sistemi sotto contratto. Non vendiamo mai i tuoi dati."],
        },
        {
          id: "retention",
          heading: "Per quanto tempo li conserviamo",
          paragraphs: ["I dati di prenotazione e contabili sono conservati per il periodo previsto dalla legge fiscale (fino a 10 anni). Le ricevute di pagamento sono conservate in modo riservato ed eliminate quando non servono più. I dati dell'account restano finché non ci chiedi di eliminarlo."],
        },
        {
          id: "rights",
          heading: "I tuoi diritti",
          paragraphs: ["Puoi chiedere accesso, rettifica, cancellazione o portabilità dei dati, opporti al trattamento o revocare il consenso. Scrivici dall'email collegata alla prenotazione. Se risiedi nell'UE o nel Regno Unito puoi anche presentare reclamo all'autorità di controllo."],
        },
      ],
    },
  },

  "cookie-policy": {
    en: {
      title: "Cookie policy",
      description: "Which cookies and local storage Sharm Station Viaggi uses and how to manage them.",
      intro: "We use a small number of cookies and browser storage items. Essential ones make the website work; optional ones are used only if you accept them in the cookie banner.",
      updated: UPDATED,
      sections: [
        {
          id: "essential",
          heading: "Essential",
          paragraphs: ["These are needed for the website to work and cannot be switched off:"],
          list: [
            "NEXT_LOCALE: remembers your language (1 year).",
            "ssv_currency: remembers the currency you chose (1 year).",
            "ssv_cookie_consent (local storage): remembers your cookie choice.",
            "ssv_session and ssv_signed_in: keep you signed in until you sign out (30 days). The sign-in token in ssv_session cannot be read by scripts on the page.",
            "Checkout and booking data (session storage): keeps your booking details while you complete checkout; deleted when you close the tab.",
          ],
        },
        {
          id: "preferences",
          heading: "Preferences",
          paragraphs: ["ssv_favourites (local storage) keeps the trips and rooms you saved with the heart button, on this device only."],
        },
        {
          id: "analytics",
          heading: "Statistics",
          paragraphs: ["If you accept all cookies, we may use privacy-friendly statistics to understand which pages are useful. We do not use advertising cookies."],
        },
        {
          id: "manage",
          heading: "Managing your choice",
          paragraphs: ["You can change your choice at any time by clearing this site's data in your browser settings; the cookie banner will appear again. Blocking essential cookies may stop sign-in and checkout from working."],
        },
      ],
    },
    it: {
      title: "Cookie policy",
      description: "Quali cookie e archiviazione locale usa Sharm Station Viaggi e come gestirli.",
      intro: "Usiamo pochi cookie ed elementi di archiviazione del browser. Quelli essenziali fanno funzionare il sito; quelli facoltativi sono usati solo se li accetti nel banner dei cookie.",
      updated: UPDATED,
      sections: [
        {
          id: "essential",
          heading: "Essenziali",
          paragraphs: ["Sono necessari al funzionamento del sito e non si possono disattivare:"],
          list: [
            "NEXT_LOCALE: ricorda la lingua (1 anno).",
            "ssv_currency: ricorda la valuta scelta (1 anno).",
            "ssv_cookie_consent (archiviazione locale): ricorda la tua scelta sui cookie.",
            "ssv_session e ssv_signed_in: mantengono l'accesso finché non esci (30 giorni). Il token di accesso in ssv_session non è leggibile dagli script della pagina.",
            "Dati di checkout e prenotazione (archiviazione di sessione): conservano i dettagli mentre completi il checkout; vengono eliminati alla chiusura della scheda.",
          ],
        },
        {
          id: "preferences",
          heading: "Preferenze",
          paragraphs: ["ssv_favourites (archiviazione locale) conserva le escursioni e le camere salvate con il cuore, solo su questo dispositivo."],
        },
        {
          id: "analytics",
          heading: "Statistiche",
          paragraphs: ["Se accetti tutti i cookie, potremmo usare statistiche rispettose della privacy per capire quali pagine sono utili. Non usiamo cookie pubblicitari."],
        },
        {
          id: "manage",
          heading: "Gestire la tua scelta",
          paragraphs: ["Puoi cambiare scelta in qualsiasi momento cancellando i dati di questo sito dalle impostazioni del browser; il banner comparirà di nuovo. Bloccare i cookie essenziali può impedire accesso e checkout."],
        },
      ],
    },
  },
};
