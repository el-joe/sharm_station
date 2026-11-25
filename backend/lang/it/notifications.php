<?php

/*
| Modelli email predefiniti (SRS 15.1). Copiati in notification_templates, dove l'admin li modifica.
| Segnaposto: {{nome}}. Le righe vuote separano i paragrafi, **testo** è in grassetto.
*/

return [
    'layout' => [
        'tagline' => 'Escursioni, crociere sul Nilo e soggiorni a Sharm El Sheikh',
        'footer' => 'Ricevi questa email per la tua prenotazione con :company.',
        'footer_admin' => 'Notifica del back office di :company.',
        'footer_account' => 'Ricevi questa email per il tuo account con :company.',
        'button_fallback' => 'Se il pulsante non funziona, copia questo link nel browser:',
        'voucher_attached' => 'Il voucher è allegato a questa email in PDF.',
    ],

    'events' => [
        'booking_created' => [
            'subject' => 'Prenotazione {{reference}} ricevuta',
            'body' => "Ciao {{customer_name}},\n\ngrazie per aver prenotato **{{service}}** il {{service_date}} con {{company_name}}.\n\nTotale: **{{total}}**\nAcconto da pagare ora: **{{deposit}}**\nSaldo successivo: {{balance}}\n\nCompleta il pagamento entro il {{payment_due_at}}, altrimenti la prenotazione scade.",
            'action' => 'Completa il pagamento',
        ],
        'booking_created_admin' => [
            'subject' => 'Nuova prenotazione {{reference}} · {{service}}',
            'body' => "È stata creata una nuova prenotazione.\n\nCodice: **{{reference}}**\nServizio: {{service}} il {{service_date}}\nPrenotante: {{customer_name}} {{partner_company}}\nTotale: {{total}} · acconto {{deposit}}\nStato: {{status}}",
            'action' => 'Apri la prenotazione',
        ],
        'deposit_confirmed' => [
            'subject' => 'Prenotazione {{reference}} confermata',
            'body' => "Ciao {{customer_name}},\n\nabbiamo ricevuto il tuo pagamento: la prenotazione **{{reference}}** per **{{service}}** il {{service_date}} è confermata.\n\nPagato: **{{paid}}**\nSaldo da pagare: {{balance}} (entro il {{balance_due_on}})\n\nIn allegato trovi il voucher. A presto!",
            'action' => 'Vedi la prenotazione',
        ],
        'deposit_confirmed_admin' => [
            'subject' => 'Pagamento ricevuto per {{reference}}',
            'body' => "L'acconto della prenotazione **{{reference}}** è stato pagato.\n\nServizio: {{service}} il {{service_date}}\nPrenotante: {{customer_name}} {{partner_company}}\nPagato: {{paid}} · saldo {{balance}}",
            'action' => 'Apri la prenotazione',
        ],
        'balance_reminder' => [
            'subject' => 'Promemoria: saldo della prenotazione {{reference}}',
            'body' => "Ciao {{customer_name}},\n\nti ricordiamo che il saldo di **{{balance}}** per **{{service}}** il {{service_date}} va pagato entro il **{{balance_due_on}}**.\n\nSe hai già pagato, ignora questa email.",
            'action' => 'Vedi la prenotazione',
        ],
        'balance_recorded' => [
            'subject' => 'Prenotazione {{reference}} saldata',
            'body' => "Ciao {{customer_name}},\n\nabbiamo registrato il pagamento del saldo. La prenotazione **{{reference}}** è interamente pagata ({{paid}}) e la tua posizione è chiusa.\n\nIn allegato il voucher aggiornato.",
            'action' => 'Vedi la prenotazione',
        ],
        'balance_overdue_admin' => [
            'subject' => '{{overdue_count}} prenotazioni con saldo scaduto',
            'body' => "Queste prenotazioni hanno ancora un saldo da incassare dopo la scadenza:\n\n{{overdue_list}}",
            'action' => 'Apri le prenotazioni',
        ],
        'payment_failed' => [
            'subject' => 'Pagamento della prenotazione {{reference}} non riuscito',
            'body' => "Ciao {{customer_name}},\n\nil pagamento online della prenotazione **{{reference}}** non è andato a buon fine. Non è stato addebitato nulla.\n\nPuoi riprovare o scegliere un altro metodo di pagamento entro il {{payment_due_at}}.",
            'action' => 'Riprova',
        ],
        'booking_expired' => [
            'subject' => 'Prenotazione {{reference}} scaduta',
            'body' => "Ciao {{customer_name}},\n\nnon abbiamo ricevuto il pagamento della prenotazione **{{reference}}** ({{service}}, {{service_date}}) entro la scadenza, quindi la prenotazione è scaduta.\n\nPuoi prenotare di nuovo dal nostro sito.",
            'action' => 'Vedi la prenotazione',
        ],
        'hold_expiring' => [
            'subject' => 'Il blocco della camera per {{reference}} sta per scadere',
            'body' => "Ciao {{customer_name}},\n\nteniamo **{{service}}** bloccata per te fino alle **{{hold_expires_at}}**. Completa il pagamento di {{deposit}} entro quell'ora per non perdere la camera.",
            'action' => 'Paga ora',
        ],
        'transfer_requested_admin' => [
            'subject' => 'Richiesta di transfer {{reference}}',
            'body' => "È stato richiesto un transfer con la prenotazione **{{reference}}**.\n\n{{transfer_details}}\n\nPrenotante: {{customer_name}}\nPrezzo: {{total}} · stato {{status}}",
            'action' => 'Apri la prenotazione',
        ],
        'transfer_status_changed' => [
            'subject' => 'Transfer {{reference}}: {{transfer_status}}',
            'body' => "Ciao {{customer_name}},\n\nlo stato del tuo transfer ora è **{{transfer_status}}**.\n\n{{transfer_details}}\nAutista: {{driver_name}} {{driver_phone}}\nTarga: {{car_plate}}",
            'action' => 'Vedi la prenotazione',
        ],
        'included_transfer_admin' => [
            'subject' => 'Transfer inclusi da organizzare per {{reference}}',
            'body' => "La prenotazione del pacchetto **{{reference}}** include transfer da organizzare:\n\n{{transfer_details}}",
            'action' => 'Apri la prenotazione',
        ],
        'proof_uploaded_admin' => [
            'subject' => 'Nuova prova di pagamento per {{reference}}',
            'body' => "È stata caricata una prova di pagamento per la prenotazione **{{reference}}** ({{customer_name}}).\n\nControllala nella coda delle ricevute.",
            'action' => 'Controlla la ricevuta',
        ],
        'proof_approved' => [
            'subject' => 'Pagamento ricevuto per la prenotazione {{reference}}',
            'body' => "Ciao {{customer_name}},\n\nla tua prova di pagamento è stata approvata. Pagato finora: **{{paid}}**, saldo {{balance}}.",
            'action' => 'Vedi la prenotazione',
        ],
        'proof_rejected' => [
            'subject' => 'Prova di pagamento per {{reference}} non accettata',
            'body' => "Ciao {{customer_name}},\n\nnon abbiamo potuto accettare la prova di pagamento della prenotazione **{{reference}}**.\n\nMotivo: {{reason}}\n\nCarica una nuova prova dalla pagina della prenotazione.",
            'action' => 'Carica una nuova prova',
        ],
        'cancellation_requested_admin' => [
            'subject' => 'Richiesta di cancellazione per {{reference}}',
            'body' => "Il prenotante ha chiesto di cancellare la prenotazione **{{reference}}** ({{service}}, {{service_date}}).\n\nMotivo: {{reason}}\nPagato: {{paid}}",
            'action' => 'Decidi la richiesta',
        ],
        'cancellation_approved' => [
            'subject' => 'Prenotazione {{reference}} cancellata',
            'body' => "Ciao {{customer_name}},\n\nla tua richiesta di cancellazione è stata approvata e la prenotazione **{{reference}}** è cancellata.\n\nRimborso: **{{refund_amount}}**. Riceverai un'altra email quando verrà pagato.",
            'action' => 'Vedi la prenotazione',
        ],
        'cancellation_declined' => [
            'subject' => 'Richiesta di cancellazione per {{reference}} rifiutata',
            'body' => "Ciao {{customer_name}},\n\nnon abbiamo potuto accettare la cancellazione della prenotazione **{{reference}}**.\n\nMotivo: {{reason}}\n\nLa prenotazione resta attiva. Rispondi a questa email o invia una nuova richiesta se qualcosa cambia.",
            'action' => 'Vedi la prenotazione',
        ],
        'refund_issued' => [
            'subject' => 'Rimborso per la prenotazione {{reference}}',
            'body' => "Ciao {{customer_name}},\n\nabbiamo rimborsato **{{refund_amount}}** per la prenotazione **{{reference}}**. A seconda della banca possono servire alcuni giorni.",
            'action' => 'Vedi la prenotazione',
        ],
        'partner_application_admin' => [
            'subject' => 'Nuova richiesta partner: {{partner_company}}',
            'body' => "Una nuova richiesta partner è in attesa di revisione.\n\nAzienda: **{{partner_company}}**\nContatto: {{partner_contact}} · {{partner_email}} · {{partner_phone}}\nPaese: {{partner_country}}",
            'action' => 'Esamina la richiesta',
        ],
        'partner_approved' => [
            'subject' => 'Benvenuto tra i partner di {{company_name}}',
            'body' => "Ciao {{partner_contact}},\n\nl'account partner di **{{partner_company}}** è stato approvato. Ora puoi prenotare a prezzi partner dal pannello partner.",
            'action' => 'Apri il pannello partner',
        ],
        'partner_rejected' => [
            'subject' => 'La tua richiesta partner',
            'body' => "Ciao {{partner_contact}},\n\ngrazie per l'interesse. Al momento non possiamo approvare la richiesta partner di **{{partner_company}}**.\n\nMotivo: {{reason}}",
            'action' => '',
        ],
        'review_invitation' => [
            'subject' => 'Com\'è andata {{service}}?',
            'body' => "Ciao {{customer_name}},\n\ngrazie per aver viaggiato con {{company_name}}! Speriamo che **{{service}}** ti sia piaciuta.\n\nTi va di dedicare un minuto a raccontare la tua esperienza su Google? Aiuta altri viaggiatori ed è importante per il nostro team.",
            'action' => 'Scrivi una recensione su Google',
        ],
        'partner_statement' => [
            'subject' => 'Il tuo estratto conto di {{period}}',
            'body' => "Ciao {{partner_contact}},\n\necco l'estratto conto di **{{partner_company}}** per {{period}}.\n\nPrenotazioni: {{statement_bookings}}\nTotale partner: **{{statement_total}}**\nLa tua commissione: {{statement_commission}}\nSaldo da pagare: **{{statement_balance}}**\n\nIn allegato il PDF.",
            'action' => 'Apri i tuoi estratti conto',
        ],
        'customer_welcome' => [
            'subject' => 'Benvenuto in {{company_name}}: imposta la tua password',
            'body' => "Ciao {{customer_name}},\n\nabbiamo creato il tuo account {{company_name}} per **{{customer_email}}**, così puoi seguire le tue prenotazioni online.\n\nScegli la tua password con il pulsante qui sotto. Il link è personale e scade tra 7 giorni; dopo, usa \"Password dimenticata\" nella pagina di accesso.",
            'action' => 'Imposta la password',
        ],
        'password_reset' => [
            'subject' => 'Reimposta la password di {{company_name}}',
            'body' => "Ciao {{customer_name}},\n\nabbiamo ricevuto una richiesta di reimpostare la password del tuo account **{{customer_email}}**.\n\nScegli una nuova password con il pulsante qui sotto. Il link scade tra 60 minuti. Se non l'hai richiesto tu, ignora questa email: la tua password non cambia.",
            'action' => 'Reimposta la password',
        ],
    ],
];
