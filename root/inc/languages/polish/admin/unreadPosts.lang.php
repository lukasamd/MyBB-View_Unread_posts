<?php
/**
 * This file is part of View Unread Posts plugin for MyBB.
 * Copyright (C) 2010-2013 Lukasz Tkacz <lukasamd@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */ 

$l['unreadPostsName'] = 'Nieprzeczytane posty';
$l['unreadPostsDesc'] = 'Ten plugin dodaje funkcję "nieprzeczytane posty" dla wszystkich zarejestrowanych użytkowników.';
$l['unreadPostsGroupDesc'] = 'Ustawienia dotyczące modyfikacji "Nieprzeczytane posty"';


$l['unreadPostsExceptions'] = 'Wykluczone fora';
$l['unreadPostsExceptionsDesc'] = 'ID forów oddzielone przecinkami, w których nie będą szukane nieprzeczytane posty.';

$l['unreadPostsStatusActionUnread'] = 'Zamiana linków do ostatniego na linki do nieczytanego postu';
$l['unreadPostsStatusActionUnreadDesc'] = 'Zamienia wszystkie linki na forum prowadzące do ostatniego postu (action=lastpost) na linki prowadzące do pierwszego nieczytanego postu w temacie.';

$l['unreadPostsStatusPostbitMark'] = 'Oznaczanie nieczytanych postów w widoku tematu';
$l['unreadPostsStatusPostbitMarkDesc'] = 'Określa, czy nieczytane posty mają być oznaczane danymi z szablonu unreadPosts_postbit (domyślnie napis).';

$l['unreadPostsStatusCounter'] = 'Licznik nieprzeczytanych postów';
$l['unreadPostsStatusCounterDesc'] = 'Dodaje przy linku licznik nieprzeczytanych postów, o ile takie istnieją.';

$l['unreadPostsStatusCounterHide'] = 'Ukrywanie linku gdy nie ma nieprzeczytanych';
$l['unreadPostsStatusCounterHideDesc'] = 'Ta opcja powoduje ukrywanie linku do szukania nieprzeczytanych postów, gdy takowe nie istnieją. Działa tylko gdy włączono licznik nieprzeczytanych postów.';

$l['unreadPostsCounterPages'] = 'Podstrony z aktywnym licznikiem nieczytanych postów';
$l['unreadPostsCounterPagesDesc'] = 'Kody podstron (stała THIS_SCRIPT), na których licznik ma być aktywny. W wypadku braku zawartości licznik będzie aktywny na wszystkich stronach.';

$l['unreadPostsMarkAllReadLink'] = 'Wyświetlaj link "Oznacz wszystkie tematy jako przeczytane" w wynikach wyszukiwania';
$l['unreadPostsMarkAllReadLinkDesc'] = 'Jeżeli włączono, nad listą wyników wyszukiwania będzie wyświatlany link pozwalający na oznaczenie wszystkich tematów jako przeczytane.';

$l['unreadPostsMarkerStyle'] = 'Styl wyświetlania oznaczenia w widoku tematu';
$l['unreadPostsMarkerStyleDesc'] = 'Styl CSS oznaczenia dla nieprzeczytanych postów w temacie.';

$l['unreadPostsThreadStartDate'] = 'Wyświetlanie czasu startu wątku';
$l['unreadPostsThreadStartDateDesc'] = 'Wyświetla datę rozpoczęcia wątku w wynikach wyszukiwania obok informacji o autorze.';
