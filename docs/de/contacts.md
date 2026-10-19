# Adressbücher und Kontakte

## Umfang

Der erste Kontakt-Baustein schafft die Grundlage für lokale persönliche Adressbücher und Kontakte.

Enthalten sind:

- persönliche Adressbücher mit genau einem Eigentümer
- lokale Kontakte innerhalb eines Adressbuchs
- mehrere Telefonnummern je Kontakt
- Soft-Delete für Adressbücher und Kontakte
- ausdrückliche Eigentümerprüfung vor Lesen oder Ändern von Kontaktdaten
- transaktionssichere Anlage und Änderung von Kontakten samt Telefonnummern
- Weboberfläche für persönliche Adressbücher und Kontakte
- CSRF-geschützte Anlage-, Änderungs-, Umbenennungs- und Löschaktionen
- eigentümergebundene Suche nach Namen, Organisation, Rufnummernbezeichnung und Roh-Rufnummer

Noch nicht enthalten:

- Freigaben
- organisationsbezogene Adressbücher
- externe Kontaktquellen
- Importe
- Dublettenerkennung

## Weboberfläche

Angemeldete Benutzer können ihre eigenen Adressbücher unter `/address-books/` verwalten. Die Oberfläche unterstützt Anlegen, Umbenennen, Öffnen und logisches Löschen von Adressbüchern sowie Anlegen, Bearbeiten und logisches Löschen von Kontakten.

Kontaktformulare bieten mehrere Rufnummernfelder ohne zusätzliche JavaScript-Abhängigkeit. Nicht ausgefüllte Rufnummernfelder werden serverseitig ignoriert.

Adressbuchansichten bieten eine GET-basierte Suche über Anzeigename, Vorname, Nachname, Organisation, Rufnummernbezeichnungen, rohe Rufnummern und normalisierte E.164-Werte. Die Suche bleibt auf das bereits berechtigte Adressbuch begrenzt. SQL-LIKE-Wildcards aus Benutzereingaben werden vor der Suche escaped.

Alle zustandsändernden Aktionen verwenden POST und benötigen einen gültigen CSRF-Token. Auch Detail- und Bearbeitungsansichten werden vor der Ausgabe über die eigentümergebundenen Anwendungsservices aufgelöst.

## Eigentum und Berechtigungen

Jedes Adressbuch besitzt derzeit genau eine `owner_user_id`.

Die Anwendungsservices prüfen vor jeder Kontaktoperation, ob das angegebene Adressbuch tatsächlich dem angemeldeten Benutzer gehört.

Auch ein Systemadministrator erhält dadurch nicht automatisch Zugriff auf private Kontakte. Plattformverwaltung und private Kontaktdaten bleiben getrennt.

## Kontakte

Ein Kontakt unterstützt derzeit:

- Anzeigename
- Vorname
- Nachname
- Organisationsname
- Notizen
- mehrere Telefonnummern

Der Anzeigename ist Pflicht. Optionale Textfelder werden bereinigt; leere Werte werden als `NULL` gespeichert.

## Telefonnummern

Je Telefonnummer werden gespeichert:

- optionale Bezeichnung
- rohe/originale Schreibweise
- optional normalisierte E.164-Nummer
- Reihenfolge

Rufnummern werden mit libphonenumber normalisiert, wenn sie sicher validiert werden können. Die Rohdarstellung bleibt zusätzlich zum normalisierten Wert gespeichert.

Internationale Nummern mit führendem `+` können ohne Standardregion normalisiert werden. Lokale bzw. nationale Nummern werden nur interpretiert, wenn `contacts.default_region` ausdrücklich einen unterstützten ISO-3166-1-Alpha-2-Ländercode wie `DE` enthält. Ohne konfigurierte Region bleibt bei mehrdeutigen lokalen Nummern `normalized_e164 = NULL`, statt ein Land zu erraten.

Die Standardregion kann alternativ über `OPENPHONEBOOK_DEFAULT_PHONE_REGION` gesetzt werden. Bei Neuinstallationen steht sie im Web-Installer zur Verfügung. Bereits gespeicherte Rufnummern werden nicht stillschweigend rückwirkend verändert; die Normalisierung erfolgt beim späteren Speichern des Kontakts oder über eine zukünftige ausdrücklich gestartete Wartungs-/Importfunktion.

## Löschen und Transaktionen

Adressbücher und Kontakte werden zunächst nur logisch gelöscht.

Das Anlegen und Aktualisieren eines Kontakts einschließlich seiner Telefonnummern erfolgt innerhalb einer Datenbanktransaktion, damit kein unvollständiger Zwischenzustand gespeichert werden kann.
