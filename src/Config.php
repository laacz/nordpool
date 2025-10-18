<?php

class Config
{
    public static function getCountries(?string $country = null): array
    {
        $countries = [
            'LV' => [
                'code_lc' => 'lv',
                'code' => 'LV',
                'name' => 'Latvija',
                'flag' => '🇱🇻',
                'locale' => 'lv_LV',
                'timezone' => 'Europe/Riga',
                'vat' => 0.21,
            ],
            'LT' => [
                'code_lc' => 'lt',
                'code' => 'LT',
                'name' => 'Lietuva',
                'flag' => '🇱🇹',
                'locale' => 'lt_LT',
                'timezone' => 'Europe/Vilnius',
                'vat' => 0.21,
            ],
            'EE' => [
                'code_lc' => 'ee',
                'code' => 'EE',
                'name' => 'Eesti',
                'flag' => '🇪🇪',
                'locale' => 'et_EE',
                'timezone' => 'Europe/Tallinn',
                'vat' => 0.24,
            ],
        ];

        if ($country === null) {
            return $countries;
        }

        return $countries[$country] ?? $countries['LV'];
    }

    public static function getTranslations(): array
    {
        return [
            'Primitīvs grafiks' => [
                'LV' => 'Primitīvs grafiks',
                'LT' => 'Paprastas grafikas',
                'EE' => 'Lihtne joonis',
            ],
            'normal CSV' => [
                'LV' => 'parasts CSV',
                'LT' => 'įprastas CSV',
                'EE' => 'tavalise CSV',
            ],
            'Excel CSV' => [
                'LV' => 'Excel\'im piemērots CSV',
                'LT' => 'Excel\'ui tinkamas CSV',
                'EE' => 'Excel\'ile sobiva CSV',
            ],
            'disclaimer' => [
                'LV' => 'Dati par rītdienu parādās agrā pēcpusdienā vai arī tad, kad parādās. Avots: Nordpool day-ahead stundas spotu cenas, LV. Krāsa atspoguļo cenu sāļumu konkrētajā dienā, nevis visā tabulā. Attēlotais ir Latvijas laiks. Dati pieejami arī kā %s, kā %s vai %s. Dati tiek atjaunoti reizi dienā ap 12:00 ziemā un ap 11:00 vasarā.<br/>
        Kontaktiem un jautājumiem: <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
                'LT' => 'Ryto duomenys pasirodo ankstyvą popietę arba kai tik jie pasirodo. Šaltinis: Nordpool day-ahead valandos spot kainos, LT. Spalva atspindi kainų druskingumą konkrečią dieną, o ne visoje lentelėje. Rodomas Lietuvos laikas. Duomenys taip pat prieinami kaip %s, kaip %s, ir kaip %s. Duomenys atnaujinami kartą per dieną apie 12:00 žiemą ir apie 11:00 vasarą.<br/>
        Kontaktams ir klausimams: <a href="mailto:apps@didnt.work">apps@didnt.work</a> (pageidautina latviškai arba angliškai).',
                'EE' => 'Homme andmed ilmuvad varakult pärastlõunal või kui need ilmuvad. Allikas: Nordpool day-ahed tundide spot hinnad, EE. Värv peegeldab hinna soolsust konkreetsel päeval, mitte kogu tabelis. Kuvatakse Eesti aeg. Andmed on saadaval ka %s, %s ja %s kujul. Andmeid uuendatakse üks kord päevas umbes 12:00 paiku talvel ja umbes 11:00 suvel.<br/>
        Kontaktide ja küsimuste jaoks: <a href="mailto:apps@didnt.work">apps@didnt.work</a> (eelistatavalt läti või inglise keeles).',
            ],
            'Price shown is without VAT' => [
                'LV' => 'Atspoguļotā cena ir bez PVN',
                'LT' => 'Rodoma kaina be PVM',
                'EE' => 'Näidatud hind on ilma käibemaksuta',
            ],
            'Price shown includes VAT' => [
                'LV' => 'Atspoguļotā cena iekļauj PVN',
                'LT' => 'Rodoma kaina su PVM',
                'EE' => 'Näidatud hind on käibemaksuga',
            ],
            'subtitle' => [
                'LV' => 'Nordpool elektrības biržas SPOT cenas šodienai un rītdienai Latvijā.',
                'LT' => 'Nordpool elektros biržos SPOT kainos šiandien ir rytoj Lietuvoje',
                'EE' => 'Nordpooli elektribörsi SPOT hinnad tänaseks ja homseks Eestis',
            ],
            'it is without VAT' => [
                'LV' => 'Tās ir <strong>bez PVN</strong>',
                'LT' => 'Jie yra <strong>be PVM</strong>',
                'EE' => 'Need on <strong>ilma käibemaksuta</strong>',
            ],
            'it is with VAT' => [
                'LV' => 'Tā ir <strong>ar PVN</strong>',
                'LT' => 'Tai <strong>aipima PVM</strong>',
                'EE' => 'Need <stgrong>on käibemaksuga</strong>',
            ],
            'show with VAT' => [
                'LV' => 'rādīt ar PVN',
                'LT' => 'rodyti su PVM',
                'EE' => 'näita KM-ga',
            ],
            'show without VAT' => [
                'LV' => 'rādīt bez PVN',
                'LT' => 'rodyti be PVM',
                'EE' => 'näita ilma KM-ta',
            ],
            'Izvairāmies tērēt elektrību' => [
                'LV' => 'Izvairāmies tērēt elektrību',
                'LT' => 'Venkime švaistyti elektros energiją',
                'EE' => 'Vältige elektri raiskamist',
            ],
            'Krājam burciņā' => [
                'LV' => 'Krājam burciņā',
                'LT' => 'Kaupkime stiklainėje',
                'EE' => 'Kogume purki',
            ],
            'title' => [
                'LV' => 'Nordpool elektrības biržas cenas šodienai un rītdienai',
                'LT' => 'Nordpool elektros biržos kainos šiandienai ir rytoj',
                'EE' => 'Nordpooli elektribörsi hinnad tänaseks ja homseks',
            ],
            'Šodien' => [
                'LV' => 'Šodien',
                'LT' => 'Šiandien',
                'EE' => 'Täna',
            ],
            'Rīt' => [
                'LV' => 'Rīt',
                'LT' => 'Rytoj',
                'EE' => 'Homme',
            ],
            'Vidēji' => [
                'LV' => 'Vidēji',
                'LT' => 'Vidutiniškai',
                'EE' => 'Keskmine',
            ],
            '15min notice' => [
                'LV' => 'Sākot ar 1. oktobri, biržas cenas tiek noteiktas ar 15 minūšu soli. Iepriekš solis bija stunda. Tas nekur nav pazudis. Saite ir augšā.',
                'LT' => 'Nuo spalio 1 d. biržos kainos nustatomos 15 minučių intervalu. Anksčiau intervalas buvo valanda. Tai niekur nedingo. Nuoroda yra viršuje.',
                'EE' => 'Alates 1. oktoobrist määratakse börsihinnad 15-minutilise sammuga. Varem oli samm tund. See pole kuhugi kadunud. Link on üleval.',
            ],
            'Resolution' => [
                'LV' => 'Uzskaites solis',
                'LT' => 'Apskaitos žingsnis',
                'EE' => 'Raamatupidamise samm',
            ],
            'show 1h' => [
                'LV' => 'rādīt 1h',
                'LT' => 'rodyti 1h',
                'EE' => 'näita 1h',
            ],
            'show 15min' => [
                'LV' => 'rādīt 15min',
                'LT' => 'rodyti 15min',
                'EE' => 'näita 15min',
            ],
            '1h average' => [
                'LV' => '1h vidējie dati',
                'LT' => '1h vidutiniai duomenys',
                'EE' => '1h keskmised andmed',
            ],
            '15min data' => [
                'LV' => '15min dati',
                'LT' => '15min duomenys',
                'EE' => '15min andmed',
            ],
            'Brīdinājums' => [
                'LV' => 'Brīdinājums',
                'LT' => 'Įspėjimas',
                'EE' => 'Hoiatus',
            ],
            'automātiski' => [
                'LV' => 'automātiski',
                'LT' => 'automatiškai',
                'EE' => 'automaatselt',
            ],
            'notification announcement' => [
                'LV' => 'Augšā labajā pusē eksperimentālā kārtā var norādīt robežu, pie kuras cena tiek atzīmēta kā dārga (sarkana). Ja ir ieteikumi uzlabojumiem, droši rakstiet uz <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
                'LT' => 'Viršutiniame dešiniajame kampe eksperimentiniu būdu galite nurodyti ribą, nuo kurios kaina pažymima kaip brangi (raudona). Jei turite pasiūlymų patobulinimams, nedvejodami rašykite el. paštu <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
                'EE' => 'Eksperimentaalselt saate paremas ülanurgas määrata piiri, mille juures hind märgitakse kalliks (punane). Kui teil on parandusettepanekuid, kirjutage julgelt aadressile <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
            ],
            'app_name' => [
                'LV' => 'Nordpool elektrības cenas',
                'LT' => 'Nordpool elektros kainos',
                'EE' => 'Nordpooli elektrihinnad',
            ],
            'app_short_name' => [
                'LV' => 'Elektrības cenas',
                'LT' => 'Elektros kainos',
                'EE' => 'Elektrihinnad',
            ],
        ];
    }
}
