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

Noch nicht enthalten:

- Freigaben
- organisationsbezogene Adressbücher
- externe Kontaktquellen
- Importe
- Suche
- Dublettenerkennung
- Weboberfläche für Kontakte
- internationale Rufnummernnormalisierung

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

In diesem ersten Schritt wird bewusst nur die geprüfte Rohdarstellung gespeichert. `normalized_e164` bleibt `NULL`, bis eine echte Normalisierung mit eindeutigem Länder-/Regionenkontext vorhanden ist.

Damit werden lokale Nummern nicht fälschlich in internationale Nummern umgedeutet.

## Löschen und Transaktionen

Adressbücher und Kontakte werden zunächst nur logisch gelöscht.

Das Anlegen und Aktualisieren eines Kontakts einschließlich seiner Telefonnummern erfolgt innerhalb einer Datenbanktransaktion, damit kein unvollständiger Zwischenzustand gespeichert werden kann.
