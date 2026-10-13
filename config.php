<?php
// ====== KONFIGURIMI ======

date_default_timezone_set('Europe/Tirane');

// Databaza MySQL (XAMPP: perdoruesi "root" pa fjalekalim). Krijohet vete nga database/regjistrimi_cit.sql
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'regjistrimi_cit';
const DB_USER = 'root';
const DB_PASS = '';
const DB_SQL_FILE = __DIR__ . '/database/regjistrimi_cit.sql';

// Domeni i emailit universitar
const EMAIL_DOMAIN = 'cit.edu.al';

// Dosja e parazgjedhur ku ruhen studentet (nje dosje per cdo student).
// Mund te ndryshohet nga faqja "Ku ruhen" (settings.php) - ruhet ne databaze
const DEFAULT_STUDENTS_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'students';
const SETTINGS_FILE = __DIR__ . '/settings.json'; // vetem per kalimin nga versioni i vjeter

// Dosja e programit per fotot e vogla te listes, studentet e fshire dhe kopjet e vjetra (JSON)
const DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';

// Raundet, nivelet (Bachelor / Master) dhe programet ruhen ne databaze dhe ndryshohen te faqja ⚙ Admin.

// Modeli Word (placeholder-at: {{EMRI_PLOTE}}, {{EMAIL}}, {{PASSWORD}}, {{DEGA}})
const TEMPLATE_DOCX = __DIR__ . '/templates/template.docx';

// Modelet e fjalëkalimit që shfaqen në listë (shtoni ose hiqni sipas nevojës).
// Fjalët e veçanta:
//   {Emri} Ana   {emri} ana   {EMRI} ANA
//   {Mbiemri} Hoxha   {mbiemri} hoxha   {MBIEMRI} HOXHA
//   {E} D (germa e parë e emrit)   {M} H (germa e parë e mbiemrit)   {e} d   {m} h
//   {NUM} shifrat (1234, 123456 ose të rastësishme)   {VITI} viti aktual, p.sh. 2026
const PASSWORD_TEMPLATES = [
    '{Emri}.{NUM}',
    '{Emri}@{NUM}',
    '{Emri}{NUM}!',
    '{Emri}.{Mbiemri}{NUM}',
    '{Emri}{M}.{NUM}',
    '{E}{mbiemri}.{NUM}',
    '{Mbiemri}.{NUM}',
    '{Emri}{VITI}!',
    '{Emri}@{VITI}',
];

// Sa shifra ka {NUM} si parazgjedhje (mund të ndryshohet në faqe: 4, 5, 6 ose 8)
const DEFAULT_PASSWORD_DIGITS_LENGTH = 4;
const PASSWORD_DIGITS_LENGTHS = [4, 5, 6, 8];

// Viti akademik i parazgjedhur
const DEFAULT_ACADEMIC_YEAR = '2026-2027';

// Vitet ne listen "Viti akademik": nga ky vit e tutje (lista zgjatet vete me kalimin e viteve)
const ACADEMIC_YEARS_FROM = 2025;
// Sa vite perpara shfaqen pas vitit aktual
const ACADEMIC_YEARS_AHEAD = 10;
