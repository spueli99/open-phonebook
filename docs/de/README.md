# Open Phonebook – deutsche Dokumentation

Die Entwicklungs- und Architektur-Dokumentation des Projekts wird grundsätzlich auf Englisch geführt. Die Benutzeroberfläche und die Anwenderdokumentation sollen mindestens Deutsch und Englisch unterstützen.

## Aktueller Stand

Open Phonebook befindet sich in der Foundation-Phase. Es gibt noch keine stabile Version für den Produktivbetrieb.

## Dokumentation

- [Einrichtung](setup.md)
- [Anmeldung und Sitzungen](authentication.md)
- [Benutzerverwaltung](user-management.md)
- [Adressbücher und Kontakte](contacts.md)
- [Kontaktimporte](imports.md)
- [Google Kontakte](google-contacts.md)
- [Geräte](devices.md)

## Grundprinzipien

- universeller, herstellerneutraler Kern
- mehrere Benutzer und optionale Organisationen
- private und gemeinsam nutzbare Adressbücher
- lokale Kontakte sowie externe Quellen
- Yealink zunächst als erster Geräteadapter
- SIP-/Call-Events ohne PBX-Zwang
- Benachrichtigungen als austauschbare Provider
- MariaDB/MySQL und PHP 8.3+
- Web-Installer für Shared Hosting
- Deutsch und Englisch für UI/Anwenderdokumentation
- strikte Trennung von Secrets und öffentlichem Webroot
