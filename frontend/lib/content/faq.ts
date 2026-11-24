/**
 * OFFLINE FALLBACK ONLY: the website reads this content from the API (lib/api/content.ts).
 * This copy is served only when the API is unreachable (see lib/content/fallback.ts).
 */
import type { Locale } from "../api/types";

export interface FaqCategory {
  id: string;
  title: string;
  items: { question: string; answer: string }[];
}

export const FAQ: Record<Locale, FaqCategory[]> = {
  en: [
    {
      id: "booking",
      title: "Booking",
      items: [
        { question: "Can I book without an account?", answer: "Yes. You can book any trip or room as a guest. Your confirmation email contains a private link to view and pay your booking. An account simply keeps all your bookings and favourites in one place." },
        { question: "What is the difference between a private and a group trip?", answer: "A private trip is just for your party, with a vehicle and guide only for you and a price that depends on how many you are. A group trip runs on set days with other travellers and is priced per person." },
        { question: "How are children's prices calculated?", answer: "Each trip and building has age bands. Depending on the age you enter, a child travels free, pays a child price or pays the adult price. You see the result line by line before you book." },
        { question: "Can I book several rooms in one booking?", answer: "Yes. Add the guests and we suggest how to split them across rooms; you can also choose rooms yourself as long as each room's capacity is respected." },
      ],
    },
    {
      id: "payments",
      title: "Payments",
      items: [
        { question: "How much do I pay today?", answer: "Only the deposit shown at checkout, usually 30% of the total. The balance is due before the service, as shown on your voucher." },
        { question: "Which payment methods do you accept?", answer: "Cards and wallets through our online payment providers, and bank transfer or InstaPay with an uploaded proof of payment. Available methods are shown on your booking page." },
        { question: "I paid by bank transfer. What happens next?", answer: "Upload a photo or PDF of the receipt on your booking page. Your booking shows \"Awaiting receipt review\" until our team checks it, usually within a few working hours. You then receive your voucher by email." },
        { question: "In which currency am I charged?", answer: "Prices are set in euro. If you choose another currency, the exchange rate is fixed at the moment you book, so the amount does not change later." },
      ],
    },
    {
      id: "rooms",
      title: "Rooms and children",
      items: [
        { question: "Are city taxes included?", answer: "Each room card says \"Tax included\" or shows the tax per night that is added at checkout." },
        { question: "Can children share a room with adults?", answer: "Yes, within the room's capacity. Some buildings offer an extra bed for a fee, shown on the room page." },
      ],
    },
    {
      id: "transfers",
      title: "Transfers",
      items: [
        { question: "Is hotel pick-up included in day trips?", answer: "Most day trips include pick-up and drop-off from hotels in the destination; the timeline on each trip shows it. Packages include every transfer between stages." },
        { question: "Can you collect me from the airport?", answer: "Yes. Request a transfer from any airport or station; you get a live price by vehicle type, and the driver tracks your flight." },
      ],
    },
    {
      id: "cancellations",
      title: "Cancellations",
      items: [
        { question: "Can I cancel my booking?", answer: "Yes. Each product shows its cancellation terms. Many trips have free cancellation up to 24 hours before departure. Send us your reference and we confirm the refund in writing." },
        { question: "What if the weather cancels my trip?", answer: "If we cancel because of weather or an official closure, you choose another date, an equivalent alternative or a full refund." },
      ],
    },
  ],
  it: [
    {
      id: "booking",
      title: "Prenotazione",
      items: [
        { question: "Posso prenotare senza account?", answer: "Sì. Puoi prenotare qualsiasi escursione o camera come ospite. L'email di conferma contiene un link privato per vedere e pagare la prenotazione. Un account serve solo a tenere prenotazioni e preferiti in un unico posto." },
        { question: "Che differenza c'è tra escursione privata e di gruppo?", answer: "L'escursione privata è solo per il tuo gruppo, con veicolo e guida dedicati e un prezzo che dipende da quanti siete. Quella di gruppo si svolge in giorni prestabiliti con altri viaggiatori e ha un prezzo a persona." },
        { question: "Come si calcolano i prezzi dei bambini?", answer: "Ogni escursione e struttura ha fasce d'età. In base all'età indicata, il bambino viaggia gratis, paga la tariffa bambino o la tariffa adulto. Vedi il risultato riga per riga prima di prenotare." },
        { question: "Posso prenotare più camere in una sola prenotazione?", answer: "Sì. Aggiungi gli ospiti e ti suggeriamo come distribuirli nelle camere; puoi anche scegliere tu, rispettando la capienza di ogni camera." },
      ],
    },
    {
      id: "payments",
      title: "Pagamenti",
      items: [
        { question: "Quanto pago oggi?", answer: "Solo l'acconto indicato al checkout, di solito il 30% del totale. Il saldo è dovuto prima del servizio, come indicato sul voucher." },
        { question: "Quali metodi di pagamento accettate?", answer: "Carte e wallet tramite i nostri fornitori di pagamento online, e bonifico o InstaPay con caricamento della ricevuta. I metodi disponibili sono indicati nella pagina della prenotazione." },
        { question: "Ho pagato con bonifico. E adesso?", answer: "Carica una foto o un PDF della ricevuta nella pagina della prenotazione. Lo stato resta \"Ricevuta in verifica\" finché il team non la controlla, di solito entro poche ore lavorative. Poi ricevi il voucher via email." },
        { question: "In che valuta vengo addebitato?", answer: "I prezzi sono in euro. Se scegli un'altra valuta, il tasso di cambio è fissato al momento della prenotazione e l'importo non cambia in seguito." },
      ],
    },
    {
      id: "rooms",
      title: "Camere e bambini",
      items: [
        { question: "La tassa di soggiorno è inclusa?", answer: "Ogni camera indica \"Tasse incluse\" oppure la tassa per notte che viene aggiunta al checkout." },
        { question: "I bambini possono dormire in camera con gli adulti?", answer: "Sì, nei limiti della capienza della camera. Alcune strutture offrono un letto aggiunto a pagamento, indicato nella pagina della camera." },
      ],
    },
    {
      id: "transfers",
      title: "Transfer",
      items: [
        { question: "Il prelievo in hotel è incluso nelle escursioni?", answer: "La maggior parte delle escursioni include prelievo e rientro dagli hotel della destinazione; lo vedi nel programma di ogni escursione. I pacchetti includono tutti i transfer tra le tappe." },
        { question: "Potete venirmi a prendere in aeroporto?", answer: "Sì. Richiedi un transfer da qualsiasi aeroporto o stazione; ricevi un prezzo immediato per tipo di veicolo e l'autista segue il tuo volo." },
      ],
    },
    {
      id: "cancellations",
      title: "Cancellazioni",
      items: [
        { question: "Posso cancellare la prenotazione?", answer: "Sì. Ogni prodotto indica le proprie condizioni di cancellazione. Molte escursioni hanno cancellazione gratuita fino a 24 ore prima. Inviaci il codice e confermiamo il rimborso per iscritto." },
        { question: "E se il meteo annulla l'escursione?", answer: "Se annulliamo per meteo o chiusure ufficiali, scegli un'altra data, un'alternativa equivalente o il rimborso completo." },
      ],
    },
  ],
};
