=== TransferPilot — Transfer Fare & Enquiries ===
Contributors: bauveris
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 0.9.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

BAUVERIS TransferCalc 0.9.3. Lokale Abschlussprüfungen dokumentiert.
Plattform-Checkout und Veröffentlichung separat prüfen.

== Beschreibung ==
Ein eigenständig entwickeltes WordPress-Plugin für Transferpreise und
unverbindliche Fahrtanfragen. Benutzeroberfläche und Verwaltung auf Deutsch und Englisch. Sprache unter
TransferPilot > Einrichtung > Sprache / Language wählen. Eigene Texte werden
nicht automatisch übersetzt. Englische Anleitung: README-EN.txt.
Keine Abhängigkeit von Elementor, WooCommerce oder einem speziellen Theme.
Shortcode: [transferpilot]

== Installation und Einrichtung ==
1. transferpilot-0.9.3.zip unter Plugins > Installieren > Plugin hochladen installieren.
2. Aktivieren. Unter TransferPilot > Einrichtung Anzeigename, Akzentfarbe,
   Benachrichtigungsempfänger, Datenschutz-URL und Währung eintragen.
3. Orte/Flughäfen und Fahrzeugklassen hinzufügen und speichern.
4. Festpreisstrecken mit den bereits gespeicherten Orten und Fahrzeugen anlegen.
   Leeres Fahrzeugpreisfeld bedeutet: auf dieser Strecke nicht angeboten.
5. Preise und Preiszusammensetzung kontrollieren. Es sind Betreiber-Endbeträge;
   es werden KEINE zusätzlichen Steuern automatisch berechnet.
6. [transferpilot] in einen Shortcode-Block einer WordPress-Seite einfügen.
7. Testanfrage senden; im Dashboard prüfen; tatsächliche Mailzustellung testen.
   Die Empfängeradresse ist in der Auslieferung leer. Eine SMTP-Konfiguration
   ist je nach Hosting erforderlich. Plugin speichert Anfragen auch bei Mailfehlern.
8. Cache für die Formularseite ausschließen. Öffentliche Sicherheits-Nonces
   laufen ab. REST-Endpunkte dürfen nicht vom Cache bedient werden.
9. WordPress-Zeitzone als Ortszone setzen, z. B. Europe/Berlin, nicht nur UTC+1.
   Der Rechner zeigt diese eine Betriebszeitzone ausdrücklich an.

WordPress Multisite ist in dieser Version nicht unterstützt/geprüft.
Netzwerkweite Aktivierung nicht verwenden. Einzelseiten-Installation verwenden.

== Optionale Straßenberechnung ==
Standard: feste Streckenpreise, ohne externen Kartendienst.
Optional unter Einrichtung beide Berechnungsarten aktivieren, eigene Tarife für
Grundpreis, Preis je Kilometer, Mindestpreis und einen eigenen Google-Schlüssel
eintragen. Google Cloud: Routes API aktivieren und Abrechnung einrichten.
Der Schlüssel wird nur serverseitig benutzt und nicht im Formular ausgegeben.
Schlüssel liegt als nicht automatisch geladenes WordPress-Optionenfeld vor;
er ist nicht zusätzlich verschlüsselt. WordPress-Datenbank/Backups schützen.
Den Schlüssel auf die Routes API und nach Hosting-Möglichkeit Server-IPs
beschränken. Abfragequoten im Google-Konto setzen. Das Plugin liefert keine
automatische Budgetgarantie. Anbieterpreise können sich ändern.

Standard: DRIVE/TRAFFIC_UNAWARE. Optional TRAFFIC_AWARE zur Abholzeit
(Google Pro), Places API (New) für Adressvorschläge und eine Klick-Streckenkarte
mit separatem Maps-JavaScript-Browserschlüssel. Server-Schlüssel bleiben geheim.
Adressen/Suchfragmente werden an Google übertragen. Details und Kosten:
GOOGLE-FUNKTIONEN-DE-EN.txt.
Bei Rückfahrt werden beide Richtungen separat beim Anbieter angefragt.
Keine Ersatzberechnung mit Luftlinie oder erfundenen Strecken bei einem Fehler.
Keine dauerhafte Google-Routendatenbank: Routenergebnisse werden nur für die aktuelle Berechnung angezeigt und im
kurzlebigen Preis-Token gehalten. Gespeicherte Anfragen enthalten keine Google-
Distanz-/Dauerwerte; enthalten sind Betreiberpreise und vom Kunden eingegebene Orte. Vor Verkauf/Livebetrieb sind
die dann gültigen Google-Bedingungen zur Aufbewahrung/Attribution sowie die
passenden Betreiber-Datenschutzhinweise zu prüfen. Aktueller Prüfstatus siehe
PRUEFSTATUS.txt. Es wurde kein fremder oder BAUVERIS-API-Schlüssel mitgeliefert.

== Preisregeln ==
Alle Geldwerte intern als ganze Cent-/Rappeneinheiten. EUR, USD, GBP, CHF, TRY.
Diese Währungen verwenden zwei Nachkommastellen. Keine Währungsumrechnung.
Festpreis: Fahrzeugpreis der ausgewählten Strecke. Hin- und Rückfahrt: zweimal
derselbe Festpreis. Fahrtzeiten werden je Richtung auf Nachtzuschlag geprüft.
Straßenroute: max(Mindestpreis, Grundpreis + km * Kilometerpreis), je Richtung.
Streckenbetrag wird auf eine Cent-Einheit gerundet. Nachtzuschlag je Fahrt.
Extras: jeweils EINMAL pro kompletter Anfrage, auch bei Rückfahrt. Keine Mengen.
Keine automatische Maut-, Park-, Flughafen-, Steuer- oder Wartezeitberechnung.
Solche enthaltenen/nicht enthaltenen Kosten im Preishinweis deutlich benennen.
Andere Tarifmodelle sind nicht stillschweigend durch diese Formel abgedeckt.

== Speicherung und Verwaltung ==
Eigene Datenbanktabelle {prefix}tp_enquiries. Private Adminansicht nur für
Benutzer mit manage_options. Status Neu, In Bearbeitung, Abgeschlossen.
Statusänderungen bestätigen keine Fahrt und lösen keine Kunden-E-Mail aus.
Benachrichtigungsmail enthält eine Referenz und Link zur geschützten Verwaltung.
Mailstatus 'Angenommen' = wp_mail hat übernommen, kein Zustellnachweis.
Kein automatischer Mailversand an den anfragenden Fahrgast in dieser Version.
CSV-Export ist vor Tabellenformel-Injektion geschützt; enthält personenbezogene
Daten und muss vom Betreiber entsprechend geschützt/gelöscht werden.
Anfragen sind gegen manipulierte Preiswerte durch signierte 15-Minuten-Tokens
geschützt. Preisänderung erfordert neue Berechnung. Gleicher Token speichert
nicht doppelt und löst nicht mehrfach Benachrichtigungen aus.
Honeypot, öffentliche Nonce, Herkunftsprüfung und IP-basierte Ratenbegrenzung:
30 Preisberechnungen und 8 Anfrageversuche je Peer-IP innerhalb von 15 Minuten.
Bei Proxybetrieb muss die Server-IP-Konfiguration geprüft werden; keine
ungeprüften Forwarded-Header werden als vertrauenswürdig übernommen.

== Datenschutz und Löschung ==
WordPress-Datenschutzexport und -löschung werden unterstützt. Standardfrist:
90 Tage, einstellbar 1–730 Tage. Tägliche Löschung über WP-Cron; zuverlässigen
Server-Cron konfigurieren. Backups und verschickte Nachrichten sind separat zu
verwalten. Rollen/Hosting/Transportverschlüsselung liegen beim Betreiber.
Deaktivieren stoppt die automatische Löschung. Plugin-Löschen entfernt den
Google-Schlüssel, bewahrt Einstellungen und Anfrage-Daten absichtlich gegen
versehentlichen Verlust. Vor dauerhafter Deinstallation Anfragen im Dashboard
oder über Datenschutzwerkzeuge löschen; verbleibende Optionen/Tabelle ggf.
nach Datensicherung fachkundig entfernen. Nicht deinstallieren und vergessen.
Kein Analyse-Tracking, keine Telemetrie und kein öffentlicher Zugriff auf Listen.

== Funktionsgrenzen ==
Dies ist ein Preis- und Anfrageprodukt, kein vollständiges Dispositionssystem.
Keine verbindliche Fahrzeugverfügbarkeit, keine Fahrerzuordnung, keine Zahlung,
keine Flugstatusabfrage, keine automatische Buchungszusage, keine Rechnung.
Keine behauptete Gleichwertigkeit mit dem Gesamtumfang von Konkurrenzprodukten.
Name TransferPilot ist ein Arbeitsname; Marken-/Domainprüfung steht noch aus.

== Gestaltung / Einbindung ==
Farben konfigurierbar, Systemschriften ohne externe Font-Anfragen. Eigene SVG-
Fahrzeugzeichnungen und Streckenillustration; keine fremden Fotos/Lizenzen nötig.
Styles und Skripte nur auf Seiten mit verwendetem Shortcode laden.
Mehrere Instanzen pro Seite sind technisch unabhängig; zur klaren Bedienung
empfiehlt sich genau ein Rechner pro Seite. Tastaturbedienung, Beschriftungen,
Fokuswechsel, Statusmeldungen und prefers-reduced-motion werden berücksichtigt.

== Updates und Support ==
0.9.3: Adressvorschläge, Verkehrsprognose und optionale Streckenkarte.
Manuelle ZIP-Updates; kein automatischer Update-/Lizenzserver.
12 Monate Einrichtungssupport und verfügbare Funktionsupdates.
Details: SUPPORT-DE-EN.txt. Gesetzliche Rechte bleiben unberührt.
Aktuelle Tests und Grenzen: RELEASE-TESTS-DE-EN.txt.
