=== Nexi XPay Build ===
Contributors: Nexi Payments SpA
Tags: nexi, nexi payments, xpay, payment gateway, e-commerce, ecommerce, woocommerce nexi, x-pay, xpay, x-pay easy, x-pay pro, payment, card payments, e-commerce payment gateway, online shop, shopping cart, virtual POS, woocommerce payment gateway, wordpress payment gateway, Nexi credit card, Nexi payment
Author URI: https://ecommerce.nexi.it
Author: Nexi Payments SpA
Requires at least: 4.4.0
Tested up to: 6.5.4
WC Requires at least: 2.7.0
WC Tested up to: 8.9.3
Stable tag: 7.3.4
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Questo modulo permette di collegare il tuo sito e-commerce al gateway di pagamento XPay.
XPay è il servizio di Nexi che ti consente di accettare i pagamenti online e mobile in modo semplice e veloce.

XPay è il gateway di pagamento di Nexi, gruppo leader in Italia con lo scopo di costruire il futuro dei pagamenti digitali.

Nexi parte da un posizionamento consolidato sul mercato, che le permette di gestire, 44 mln di carte di pagamento, transazioni per un totale di 186 miliardi di euro, 860 mila punti vendita convenzionati in Italia, di cui oltre 18 mila ecommerce che utilizzano il gateway di pagamento XPay.

XPay ti permette di accettare pagamenti online, è aperto a tutti i circuiti di pagamento più diffusi ed è in grado di gestire tutti i canali di vendita (e-commerce, mobile).

L’estensione XPay per Wordpress/WooCommerce, ti permette di integrare il gateway di pagamento XPay senza ulteriori implementazioni sul tuo sito.  Gestisce il passaggio del cliente dal tuo sito all’ambiente sicuro Nexi e viceversa. Il cliente resta sul tuo e-commerce fino al momento del checkout e viene poi reindirizzato in ambiente sicuro Nexi per effettuare il pagamento. Non devi gestire nessun tipo di dato sensibile.

L’estensione è costantemente aggiornata con le evoluzioni e i miglioramenti di XPay.

**Per utilizzare l’estensione, è necessario un account XPay: puoi ottenere il tuo account facilmente e completamente online al sito https://ecommerce.nexi.it/.**

== Funzionalità del modulo XPay ==

-	**Pagamento Semplice:** reindirizzamento del cliente al gateway di pagamento sicuro di Nexi.
-	**Pagamento OneClick:** consente al cliente finale di memorizzare i dati della propria carta di credito, ed utilizzarli successivamente per effettuare gli acquisti con un solo click.
-	**Pagamento Ricorrente**: consente all'esercente di tokenizzare i dati della carta del cliente, in modo da poter effettuare domiciliazioni e ricorrenze per abbonamenti o altri servizi. Per poter abilitare i pagamenti ricorrenti è necessario installare l’estensione WooCommerce Subscriptions.
-	**Pay-By-Link:** permette di inviare al cliente via mail o tramite social un link che rimanda alla pagina di pagamento.
-	**Gestione delle transazioni** sui circuiti internazionali **Visa**, **Mastercard**, Visa Electron, V-Pay, Maestro, **American Express**.
-	**Accettazione di sistemi di pagamento alternativi** (**PayPal**, Amazon Pay, **Apple Pay**, **Google Pay**, **Bancomat Pay**, MyBank, iDeal, Bancontact, GiroPay ecc…)
-	**Easy checkout:** interfaccia di pagamento semplice e intuitiva (pagina di cassa Nexi con logo dell’esercente)
-	**Area di test:** permette di testare il corretto funzionamento del modulo, utilzzando parmetri e carte fittizie, senza dover effettuare transazioni reali.
-	**Multilingua:** riconosce la lingua del portale dell’esercente, e automaticamente la imposta nella pagina di cassa di XPay.
-	**Gestione Contabilizzazione:** possibilità di configurazione della modalità di incasso, totale o parziale, immediata o differita, direttamente dal modulo.
-	**Operazioni di back-office:** permette di effettuare operazione di storno e annullo direttamente dalla gestione ordine del CMS senza la necessità di accedere al backoffice di XPay .
- 	**3D Secure 2:** adottato dai principali circuiti internazionali (Visa, MasterCard, American Express), introduce nuovi metodi di autenticazione, in grado di migliorare e velocizzare l'esperienza di acquisto del titolare della carta.


*In base al tuo convenzionamento e alla configurazione impostata per il plug-in, la disponibilità delle funzionalità di XPay potrebbe cambiare. Per maggiori informazioni ti invitiamo a consultare le specifiche tecniche presenti nel portale sviluppatori del servizio sottoscritto. Trovi il link delle specifiche tecniche nella welcome mail ricevuta all’attivazione del servizio.*


Per ulteriori informazioni consulta il sito https://ecommerce.nexi.it

== Installation ==
1. Controlla la compatibilità del plugin Nexi XPay con la versione di Woocommerce/Wordpress installata nel tuo sito
2. Vai nell'area amministrativa di WordPress e fai clic su Plugin » Aggiungi nuovo.
3. Clicca sul pulsante Carica plugin nella parte superiore della pagina
4. Cerca il file .zip del plugin nel tuo computer.
5. Clicca sul pulsante Installa ora e attiva il plugin tramite il pulsante di attivazione.
6. Per il corretto funzionamento del plugin è necessario che nel server sia attiva l'estensione PHP bcmath. Nel caso venga mostrato  l'errore della   mancanza di questa estensione nel back office Wordpress, contattare l'hosting del sito.

= English =
1. Check the compatibility of the Nexi XPay plugin with the version of Woocommerce/Wordpress installed on your site
2. Go to your WordPress admin area and click on Plugins » Add New.
3. Click on the Upload Plugin button on top of the page.
4. Browse for the plugin .zip file on your computer.
5. Click on the install now button and then hit the activate button.
6. For the plugin to work correctly, the bcmath PHP extension must be active on the server. In case the error of missing this extension is shown in the Wordpress back office, contact the hosting of the site.

== Configurazione ==
1. Vai nelle impostazioni di WooCommerce e clicca su "Pagamenti".
2. Clicca su Nexi XPay per procedere con la configurazione.
3. Abilita il metodo di pagamento.
4. Scegliere tra le opzioni Alias – Chiave Mac oppure APIKey in base al servizio sottoscritto.
5. Abilita/Disabilita la modalità di test per testare il modulo con le credenziali di test fornite da Nexi.
6. Cliccare su Salva.

**Aggiungere/rimuovere metodi di pagamento alternativi**
Per aggiungere o rimuovere metodi di pagamento alternativi è necessario accedere al back office XPay. Se si effettuano delle modifiche, è necessario eseguire un salvataggio nella configurazione del modulo XPay in modo da aggiornare i metodi di pagamento.

**Bancomat Pay**
Il plugin aggiorna lo stato degli ordini tramite una notifica inviata dai server Nexi, se il modulo non riceve la notifica correttamente, non sarà in grado di aggiornare lo stato. 
Di default, in caso di problemi sulla notifica (sito non raggiungibile, errore ricevuti dal sito dell'esercente), la transazione viene annullata, anche se il pagamento si conclude con esito positivo. Questo comportamento permette che lo stato delle transazioni Nexi sia allineato con lo stato degli ordini nel CMS. 
L'opzione di annullo transazione in caso di notifica fallita, non è disponibile con il metodo di pagamento Bancomat Pay: in caso di anomalie sulla notifica ci potrebbero quindi essere dei disallinemaneti tra lo stato dell'ordine restituito dal plugin e lo stato effettivo della transazione lato Nexi. Si potrebbe quindi riscontrare il seguente scenario: il pagamento lato Bancomat Pay viene effettuato correttamente con esito positivo, ma a causa di un problema sulla notifica il plugin non è in grado di aggiornare lo stato dell'ordine mettendolo in elaborazione. 
Con questo metodo di pagamento non è previsto lo storno delle transazioni: in caso di rimborso l'esercente dovrà procedere con bonifico o altro metodo.


== Changelog ==

= 1.0.5 =
 * First Public Release.

= 1.0.6 =
 * Updated - Icon for Plugin.

= 1.0.7 =
 * Updated - Icon, Banner and Description for Plugin.
 * Tested on WordPress 4.7.2

= 1.0.8 =
* Updated - Plugin Descriptions

= 1.0.9 =
 * Updated - Plugin Descriptions

= 1.0.10 =
 * Updated - FAQ Descriptions
 * Added - Tags for Plugin

= 1.1.0 =
 * Added - New test-mode

= 1.1.3 =
 * Fixed - MAC on return

= 1.1.4 =
 * Fixed - MAC on return

= 2.0.0 =
 * Updated - Nexi Payments Refactor

= 2.0.1 =
 * Updated - Portuguese language on payment page

= 3.0.0 =
 * Updated - New configuration interface
 * Added - Choice of payment accounting (immediate / deferred)
 * Added - Support for recurring payments with WooCommerce Subscription plugin
 * Added - Gateway disabled if the payment currency is not EUR
 * Added - Refund payment via Nexi XPay API
 * Added - Payment info and XPay accounting transactions directly in WooCommerce order details

= 3.1.0 =
 * Added - OneClick: possibility for the end customer to register the credit card and be able to make subsequent payments without re-entering the data
 * Added - Ability to customize the message of payment via Nexi

= 3.1.3 =
 * Fixed - install problem
 * Fixed - payment method view problem

= 3.1.5 =
 * Fixed - view problem without WooCommerceSubscription plugin active

= 3.1.6 =
 * Fixed - incompatibility issues

= 3.2.0 =
 * Fixed - HTTP code in POST notification

= 3.3.0 =
 * Updated - Payment method also available for the exchange rate method for recurrences or in case of renewal of the overdue payment method
 * Fixed - issue with is_woocommerce_active function
 * Added - Add method description in Woocommerce payment method list
 * Fixed - duble order notification
 * Added - Add module version variable
 * Fixed - PHP notice error
 * Added - Add actions in log

= 3.3.1 =
 * Updated - list of new alternative methods available

= 3.3.2 =
 * Updated - S2S Notification retun HTTP status 500 in case of error
 * Added - Add actions in log

= 3.3.3 =
 * Fixed - view "my payment method" page in "myaccount" page

= 3.3.4 =
 * Fixed - total paid check

= 3.4.0 =
 * Added - Setting link from plugin list
 * Fixed - subscriptions payments
 * Fixed - list of payment's details in order detail page
 * Tested with WP 5.0 and WC 3.5

= 3.4.1 =
 * Fixed - configuration save

= 3.5.0 =
 * Added - Nexi image tracker
 * Updated - List of accepted payment methods downloaded automatically from the XPay merchant profile
 * Updated - Simplified configuration section
 * Fixed - PHP notice error on order's detail page

= 3.5.1 =
 * Fixed - BugFixing

= 3.6.3 =
 * Fixed - Removed constant strings to enable translations
 * Added - Improved log management

= 3.6.4 =
 * Fixed - translations in admin section

= 3.6.5 =
 * Fixed - compatibility with older versions of woocommerce

= 4.0.0 =
 * Added - 3DS 2.0 compatibility
 * Fixed - Traslation issue
 * Fixed - Minor issues
 
= 5.0.0 =
 * Changed - Management of OneClick payments: 3DSecure is required in subsequent payments.
 * Changed - Alias management in the configuration section: only one alias is required for both simple and OneClick payments.
 * Fixed - Format of the "Country" parameter sent in 3D Secure 2.0 with the ISO 3166-1 alpha-3 charset.
 * Fixed - Minor issues.
 
= 5.0.1 =
 * Fixed - Minor issues	
 
= 5.0.2 =
 * Fixed - 3D Secure 2.0 parameters format
 
= 5.1.0 =
 * Changed management of alternative payment methods available to the merchant
 * Fixed multisite issue
 * Fixed minor issues
 
= 5.2.0 =
 * Added - New payment methods Skrill, PayU, Blik, Multibanco, PoLi
 * Fixed minor issues	
 
= 6.0.0 =
 * Added - New payment method PagoDIL
 * Code refactoring
 
= 7.0.0 =
 * Added - Payment management via Api-Key
 * Code refactoring

= 7.0.1 =
 * Fixed - Minor issues

= 7.0.2 =
 * Improved logging
 * Fixed problem with recurrences

= 7.1.0 =
 * Fixed multisite issue
 * Fixed warnings and notices

= 7.1.1 =
 * Fixed - Minor issues

= 7.1.2 =
 * Fixed - Minor issues

= 7.2.0 =
 * Added - OneClick feature via Api-Key
 * Added - APM (Alternative Payment Method) support via Api-Key
 * Added - Multicurrency support for Payment Cards via Api-Key
 * Added - Multilanguage feature via Api-Key, keeps the language (if supported) between the shop and the payment gateway

= 7.2.1 =
 * Fixed - installation issue

= 7.2.2 =
 * Fixed - Minor issues

= 7.2.3 =
 * Fixed - Empty checkout fieldset
 
= 7.3.0 =
 * Added - Order lock for orders via Api-Key
 * Changed - Management of bcmath PHP library
 * Fixed - Minor issues

= 7.3.1 =
 * Fixed - Subscription issue

= 7.3.2 =
 * Added - Greek language
 * Added - Multicurrency with Apple Pay and Google Pay
 * Added - Installment payments for the Greek market
 * Fixed - Minor issues
 
= 7.3.3 =
 * Fixed - Minor issues
 
= 7.3.4 =
 * Fixed - Minor issues