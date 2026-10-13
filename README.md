# Regjistrimi i Studentëve — CIT

Program web për regjistrimin e studentëve në **Institutin Kanadian të Teknologjisë (CIT)**.
Për çdo student krijon automatikisht **emailin universitar**, **fjalëkalimin fillestar**, **letrën zyrtare Word**
dhe **foton** (me kamerë), dhe i ruan në dosje sipas vitit akademik, raundit dhe nivelit.

## Çfarë bën

- **Email automatik:** `emri.mbiemri@cit.edu.al` (ë → e, ç → c, edhe shkronja të huaja; pa dyfishime: `…2`, `…3`)
- **Fjalëkalim sipas modelit:** p.sh. `Emri.1234`, disa modele + model i lirë, 4–8 shifra fikse ose të rastësishme
- **Letra Word** nga modeli zyrtar `templates/template.docx` (`{{EMRI_PLOTE}}`, `{{EMAIL}}`, `{{PASSWORD}}`, `{{DEGA}}` + fushat shtesë)
- **Kamera** me çelës Ndezur/Fikur (foto e plotë, pa prerje) ose ngarkim fotoje
- **Dosjet:** `<dosja>\2026-2027\Raundi 1\Bachelor\Emri Mbiemri.docx` + `.jpg`
- **Lista e studentëve:** kërkim, filtra (raundi, Bachelor/Master), numërues sipas raundit dhe vitit, ✏️ ndrysho, 🗑 fshi (në kosh)
- **Printim** në A4 (Calibri 12 pt) pa hapur skedë të re; pamje e letrës në dritare pop-up dhe "Hap në Word"
- **Eksport CSV** (formati i Google Workspace) dhe **ZIP i fotove** me parapamje
- **⚙ Admin:** programet/profilet, nivelet, raundet, **fushat shtesë** të studentëve, llogaria, **backup .sql**
- **Lexon studentët ekzistues** nga letrat Word në dosjen e zgjedhur

## Kërkesat

- Windows 10/11 me **XAMPP** (Apache + **MySQL/MariaDB**, PHP 8.2 me `zip`, `mbstring`, `pdo_mysql`)
- Shfletues modern (Edge / Chrome) — kamera punon në `localhost`

## Instalimi (XAMPP)

1. Kopjoni këtë dosje te `C:\xampp\htdocs\student_regjistrim`.
2. Në XAMPP Control Panel ndizni **Apache** dhe **MySQL**.
3. Hapni `http://localhost/student_regjistrim/` → shtypni **"Krijo databazën"**
   (ose importoni `database/regjistrimi_cit.sql` në phpMyAdmin).
4. Hyni me **admin / admin** dhe ndryshoni fjalëkalimin te **⚙ Admin → Llogaria**.
5. Zgjidhni ku ruhen studentët (**📁 Ku ruhen studentët**).

Lidhja me databazën ndryshohet te `config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).

## Versioni portativ (USB, pa Apache)

Ndërtohet nga ky repo me PHP-në e XAMPP-it:

```powershell
powershell -ExecutionPolicy Bypass -File portable\build_portable.ps1
```

Krijon `portable\build\Regjistrimi_Studenteve.zip`: ekstraktohet në çdo PC me XAMPP dhe hapet me **START.bat**
(ndez vetë MySQL-in e XAMPP-it). Udhëzimet për përdoruesit: `portable/LEXO-MUA.txt`.

## Harruat fjalëkalimin?

Klikoni dy herë **`RESET_ADMIN.bat`** → llogaria kthehet në **admin / admin** (studentët nuk preken).

## Struktura

| Skedari | Çfarë bën |
|---|---|
| `index.php`, `assets/` | Faqja kryesore: formulari, kamera, lista |
| `save.php`, `update.php`, `delete.php` | Ruajtja, ndryshimi, fshirja e studentit |
| `admin.php` | Programet, nivelet, raundet, fushat shtesë, llogaria, backup |
| `settings.php` | Dosja ku ruhen studentët |
| `lib.php`, `db.php`, `auth.php`, `config.php` | Logjika, databaza, hyrja, konfigurimi |
| `print.php`, `file.php`, `open.php`, `open_doc.php` | Printimi, fotot/Word, hapja në Explorer/Word |
| `export.php`, `photos_zip.php` | Eksport CSV dhe ZIP i fotove |
| `install.php`, `login.php`, `reset_admin.php` | Krijimi i databazës, hyrja, rivendosja e llogarisë |
| `database/regjistrimi_cit.sql` | Tabelat + programet, nivelet, raundet fillestare + llogaria admin/admin |
| `portable/` | START.bat, RESET_ADMIN.bat, php.ini, udhëzimet dhe skripti i ndërtimit |

## Privatësia

Të dhënat e studentëve **nuk** ruhen në repo (shih `.gitignore`): `data/`, `students/`, `settings.json`.
Bëni backup çdo ditë: **⚙ Admin → Shkarko backup (.sql)** + kopje e dosjes së studentëve.
