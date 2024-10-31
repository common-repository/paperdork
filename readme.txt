=== Paperdork voor WooCommerce ===
Contributors: paperdork
Tags: invoices, facturen, paperdork
Requires at least: 7.0
Tested up to: 6.6.2
Stable tag: 1.9.2
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Met de Paperdork plugin kun je jouw WooCommerce webshop automatisch koppelen aan je Paperdork boekhouding. Krijg je bestellingen automatisch in je administratie en meer.

== Description ==

Wil je jouw webshop gegevens graag automatisch in je administratie verwerken? Dat kan super makkelijk met onze WooCommerce koppeling! Met onze plugin koppel je super gemakkelijk je WooCommerce account aan je Paperdork account, en kun je zelf instellen hoe je wilt dat de bestellingen in je administratie worden verwerkt. Hoe dat werkt, vertellen we je alles over in dit artikel.

=== Wat kun je allemaal automatiseren met de webshop koppeling? ===

Zodra de koppeling tussen jouw WooCommerce webshop en Paperdork is gelegd zijn er veel mogelijkheden om te automatiseren. Hieronder een aantal dingen die er mogelijk zijn:
- Zet webshop bestellingen automatisch in je administratie
- Verstuur automatisch Paperdork facturen naar je webshop klanten
- Verwerking van de juiste btw-regels voor buitenlandse facturen inclusief btw-nummer check in de checkout (let op: er bestaan altijd uitzonderingen).
- Automatisch creditfacturen maken voor terugbetalingen
... en meer!

== Installeer de webshop koppeling ==

Je kunt de Paperdork koppeling vinden in de WordPress plugin store. Vanuit daar kun je de plugin downloaden en activeren. Als je dat hebt gedaan dan moet je jouw Paperdork account nog eenmalig koppelen. Hiervoor moet je een mailtje sturen naar [hello@paperdork.nl](mailto:hello@paperdork.nl) met daarin de URL van je webshop. Wij geven je dan een client id en een secret, waarmee je de koppeling in WordPress kunt maken.

== Hoe werkt de webshop koppeling? ==

De webshop koppeling werkt eigenlijk heel simpel. Via het instellingen menu kun je zelf een aantal instellingen aanpassen om te bepalen hoe je wilt dat de bestellingen worden verwerkt in Paperdork. De meeste instellingen spreken voor zich, maar hieronder behandelen we alle instellingen nog even. Daarnaast zijn er 2 situaties waarvan het handig is dat je je hiervan bewust bent:
- Virtuele producten: als het een dienst betreft (iets dat niet fysiek verzonden hoeft te worden) dan is het belangrijk om dit in te stellen als virtueel product in WooCommerce. Diensten zorgen namelijk voor specifieke btw regels als je factureert naar het buitenland. Door er een virtueel product van te maken herkent Paperdork dat het om een dienst gaat, en wordt de factuur op de juist wijze opgesteld.
- Zakelijke klanten: zakelijke klanten kunnen bij de check-out hun btw-nummer vermelden. Als ze dat doen wordt de factuur op een zakelijke manier opgesteld (waarbij de prijzen exclusief btw worden getoond in plaats van inclusief btw). Voor buitenlandse klanten is dit helemaal belangrijk, omdat ze in dat geval geen btw hoeven te betalen. Dit wordt bij een juist btw-nummer automatisch verwerkt in het winkelmandje.

== Screenshots ==

1. Verwerk je WooCommerce bestellingen automatisch in je Paperdork account
2. Bepaal zelf hoe je jouw bestellingen in de boekhouding wilt verwerken


== Changelog ==
= 1.9.2 =
* Kleine updates doorgevoerd

= 1.9.1 =
* Kleine updates doorgevoerd

= 1.9.0 =
* We hebben een een probleem opgelost die ervoor zorgde dat de btw niet altijd goed bepaald werd bij kleine bedragen

= 1.8.4 =
* We hebben een aantal kleine problemen opgelost die ervoor konden zorgen dat bepaalde bestellingen niet automatisch werden doorgezet naar Paperdork

= 1.8.2 =
* Kleine updates doorgevoerd

= 1.8.0 =
* We hebben een proxy modus toegevoegd

= 1.7.10 =
* Als je problemen hebt met de plugin kunnen we je nu beter helpen zodra je de debugmodus hebt aangezet.

= 1.7.8 =
* We hebben een extra foutmelding toegevoegd voor als je een verkeerde Client ID of Client Secret invult.

= 1.7.6 =
* Je kan nu ook gebruik maken van het nieuwe opslagsysteem van WooCommerce

= 1.7.0 =
* SEPA incasso facturen worden nu automatisch aangemaakt waneer je gebruik maakt van Mollie
* Kleine updates doorgevoerd

= 1.6.8 =
* Kleine updates doorgevoerd

= 1.6.7 =
* Kleine updates doorgevoerd

= 1.6.6 =
* Het is vanaf nu mogelijk om bestellingen van 0 euro te negeren in de administratie (dit staat standaard ingeschakeld)

= 1.6.5 =
* Kleine updates doorgevoerd

= 1.6.2 =
* We hebben het probleem opgelost wat voor problemen kon zorgen wanneer prijzen inclusief BTW werden ingevoerd.

= 1.6.0 =
* Kleine updates doorgevoerd

= 1.6.0 =
* Creditfacturen hoeven niet meer handmatig worden aangezet

= 1.5.10 =
* Kleine updates doorgevoerd
* BTW Nummers worden altijd omgezet naar hoofdletters om fouten te voorkomen

= 1.5.9 =
* Kleine updates doorgevoerd

= 1.5.8 =
* Bug fix toegevoegd voor digitale producten
* Kleine updates doorgevoerd

= 1.5.7 =
* WooCommerce heeft een update gedaan in de manier waarop ze het land van iemand bepalen. Deze versie support deze nieuwe methode.

= 1.5.5 =
* Kleine updates doorgevoerd

= 1.5.3 =
* We hebben een instelling toegevoegd waardoor je nu adresregel 2 kan gebruiken als het huisnummer

= 1.5.2 =
* Optie om concept facturen aan te maken is verwijderd

= 1.5.1 =
* Kleine updates doorgevoerd

= 1.5.0 =
* Mail ingeschakeld die word verstuurd zodra de bestelling is afgerond

= 1.4.6 =
* Kleine updates doorgevoerd
* Support toegevoegd voor de ANSI_QUOTES database setting
* Debug modus wordt na 5 dagen automatisch uitgeschakeld

= 1.4.3 =
* Kleine updates doorgevoerd

= 1.4.0 =
* Kleine updates doorgevoerd

= 1.3.6 =
* Kleine updates doorgevoerd

= 1.3.4 =
* De foutmelding van de verbinding word pas gestuurd na 3 foutieve pogingen.

= 1.3.3 =
* Probleem opgelost waarbij facturen van klanten uit het Verenigd Koninkrijk niet goed werden aangemaakt
* Debug modus uitgebreid

= 1.3.2 =
* We hebben een debug modus toegevoegd.

= 1.3.0 =
* We hebben een check toegevoegd die je waarschuwt als de verbinding met Paperdork wegvalt.

= 1.2.6 =
* Kortingregels aangepast

= 1.2.5 =
* Bug fix

= 1.2.4 =
* Het BTW nummer is nu makkelijker te vinden in de bestelgegevens
* Probleem opgelost waardoor Nederlandse facturen niet altijd goed werden aangemaakt

= 1.2.3 =
* We hebben een aantal aanpassingen gedaan aan de achterkant van de plugin

= 1.2.2 =
* We hebben de timeout verhoogd voor onze api calls

= 1.2.0 =
* We hebben een aantal aanpassingen gedaan aan de achterkant van de plugin

= 1.1.0 =
* We hebben ondersteuning toegevoegd voor WooCommerce Subscriptions

= 1.0.9 =
* Aanpassingen gedaan in het berekenen van de kortingspercentages

= 1.0.8 =
* Waarschuwingsbericht toegevoegd bij de concept verzendstatus

= 1.0 =
* Onze eerste versie van de plugin

== Upgrade Notice ==
= 1.9.0 =
* We hebben een een probleem opgelost die ervoor zorgde dat de btw niet altijd goed bepaald werd bij kleine bedragen