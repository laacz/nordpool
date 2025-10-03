package main

import (
	"fmt"
	"time"

	"golang.org/x/text/language"
	"golang.org/x/text/message"
)

type CountryConfig struct {
	CodeLC string
	Code   string
	Name   string
	Flag   string
	Locale string
	VAT    float64
}

var countryConfigs = map[string]CountryConfig{
	"LV": {
		CodeLC: "lv",
		Code:   "LV",
		Name:   "Latvija",
		Flag:   "🇱🇻",
		Locale: "lv_LV",
		VAT:    0.21,
	},
	"LT": {
		CodeLC: "lt",
		Code:   "LT",
		Name:   "Lietuva",
		Flag:   "🇱🇹",
		Locale: "lt_LT",
		VAT:    0.21,
	},
	"EE": {
		CodeLC: "ee",
		Code:   "EE",
		Name:   "Eesti",
		Flag:   "🇪🇪",
		Locale: "et_EE",
		VAT:    0.20,
	},
}

var translations = map[string]map[string]string{
	"Primitīvs grafiks": {
		"LV": "Primitīvs grafiks",
		"LT": "Paprastas grafikas",
		"EE": "Lihtne joonis",
	},
	"normal CSV": {
		"LV": "parasts CSV",
		"LT": "įprastas CSV",
		"EE": "tavalise CSV",
	},
	"Excel CSV": {
		"LV": "Excel'im piemērots CSV",
		"LT": "Excel'ui tinkamas CSV",
		"EE": "Excel'ile sobiva CSV",
	},
	"disclaimer": {
		"LV": " Dati par rītdienu parādās agrā pēcpusdienā vai arī tad, kad parādās. Avots: Nordpool day-ahead stundas spotu cenas, LV. Krāsa atspoguļo cenu sāļumu konkrētajā dienā, nevis visā tabulā. Attēlotais ir Latvijas laiks. Dati pieejami arī kā %s un kā %s. Dati tiek atjaunoti reizi dienā ap 12:00 ziemā un ap 11:00 vasarā.<br/>Kontaktiem un jautājumiem: <a href=\"mailto:apps@didnt.work\">apps@didnt.work</a>.",
		"LT": " Ryto duomenys pasirodo ankstyvą popietę arba kai tik jie pasirodo. Šaltinis: Nordpool day-ahead valandos spot kainos, LT. Spalva atspindi kainų druskingumą konkrečią dieną, o ne visoje lentelėje. Rodomas Lietuvos laikas. Duomenys taip pat prieinami kaip %s ir kaip %s. Duomenys atnaujinami kartą per dieną apie 12:00 žiemą ir apie 11:00 vasarą.<br/>Kontaktams ir klausimams: <a href=\"mailto:apps@didnt.work\">apps@didnt.work</a> (pageidautina latviškai arba angliškai).",
		"EE": " Homme andmed ilmuvad varakult pärastlõunal või kui need ilmuvad. Allikas: Nordpool day-ahed tundide spot hinnad, EE. Värv peegeldab hinna soolsust konkreetsel päeval, mitte kogu tabelis. Kuvatakse Eesti aeg. Andmed on saadaval ka %s ja %s kujul. Andmeid uuendatakse üks kord päevas umbes 12:00 paiku talvel ja umbes 11:00 suvel.<br/>Kontaktide ja küsimuste jaoks: <a href=\"mailto:apps@didnt.work\">apps@didnt.work</a> (eelistatavalt läti või inglise keeles).",
	},
	"Price shown is without VAT": {
		"LV": "Atspoguļotā cena ir bez PVN",
		"LT": "Rodoma kaina be PVM",
		"EE": "Näidatud hind on ilma käibemaksuta",
	},
	"Price shown includes VAT": {
		"LV": "Atspoguļotā cena iekļauj PVN",
		"LT": "Rodoma kaina su PVM",
		"EE": "Näidatud hind on käibemaksuga",
	},
	"subtitle": {
		"LV": "Nordpool elektrības biržas SPOT cenas šodienai un rītdienai Latvijā.",
		"LT": "Nordpool elektros biržos SPOT kainos šiandien ir rytoj Lietuvoje",
		"EE": "Nordpooli elektribörsi SPOT hinnad tänaseks ja homseks Eestis",
	},
	"it is without VAT": {
		"LV": "Tās ir <strong>bez PVN</strong>",
		"LT": "Jie yra <strong>be PVM</strong>",
		"EE": "Need on <strong>ilma käibemaksuta</strong>",
	},
	"it is with VAT": {
		"LV": "Tā ir <strong>ar PVN</strong>",
		"LT": "Tai <strong>aipima PVM</strong>",
		"EE": "Need <strong>on käibemaksuga</strong>",
	},
	"show with VAT": {
		"LV": "rādīt ar PVN",
		"LT": "rodyti su PVM",
		"EE": "näita KM-ga",
	},
	"show without VAT": {
		"LV": "rādīt bez PVN",
		"LT": "rodyti be PVM",
		"EE": "näita ilma KM-ta",
	},
	"Izvairāmies tērēt elektrību": {
		"LV": "Izvairāmies tērēt elektrību",
		"LT": "Venkime švaistyti elektros energiją",
		"EE": "Vältige elektri raiskamist",
	},
	"Krājam burciņā": {
		"LV": "Krājam burciņā",
		"LT": "Kaupkime stiklainėje",
		"EE": "Kogume purki",
	},
	"title": {
		"LV": "Nordpool elektrības cenas (day-ahead, hourly, LV)",
		"LT": "Nordpool elektros kainos (day-ahead, hourly, LT)",
		"EE": "Nordpool elektrihinnad (day-ahead, hourly, EE)",
	},
	"Šodien": {
		"LV": "Šodien",
		"LT": "Šiandien",
		"EE": "Täna",
	},
	"Rīt": {
		"LV": "Rīt",
		"LT": "Rytoj",
		"EE": "Homme",
	},
	"Vidēji": {
		"LV": "Vidēji",
		"LT": "Vidutiniškai",
		"EE": "Keskmine",
	},
	"15min notice": {
		"LV": "Sākot ar 1. oktobri, biržas cenas tiek noteiktas ar 15 minūšu soli. Iepriekš solis bija stunda. Tas nekur nav pazudis. Saite ir augšā.",
		"LT": "Alates 1. oktoobrist määratakse börsihinnad 15-minutilise sammuga. Varem oli samm tund. See pole kuhugi kadunud. Link on üleval.",
		"EE": "Nuo spalio 1 d. biržos kainos nustatomos 15 minučių intervalu. Anksčiau intervalas buvo valanda. Tai niekur nedingo. Nuoroda yra viršuje.",
	},
	"Resolution": {
		"LV": "Uzskaites solis",
		"LT": "Apskaitos žingsnis",
		"EE": "Raamatupidamise samm",
	},
	"show 1h": {
		"LV": "rādīt 1h",
		"LT": "rodyti 1h",
		"EE": "näita 1h",
	},
	"show 15min": {
		"LV": "rādīt 15min",
		"LT": "rodyti 15min",
		"EE": "näita 15min",
	},
	"1h average": {
		"LV": "1h vidējie dati",
		"LT": "1h vidutiniai duomenys",
		"EE": "1h keskmised andmed",
	},
	"15min data": {
		"LV": "15min dati",
		"LT": "15min duomenys",
		"EE": "15min andmed",
	},
}

type Locale struct {
	Config  CountryConfig
	Printer *message.Printer
}

func NewLocale(country string) *Locale {
	config, ok := countryConfigs[country]
	if !ok {
		config = countryConfigs["LV"]
	}

	var tag language.Tag
	switch country {
	case "LT":
		tag = language.Lithuanian
	case "EE":
		tag = language.Estonian
	default:
		tag = language.Latvian
	}

	return &Locale{
		Config:  config,
		Printer: message.NewPrinter(tag),
	}
}

func (l *Locale) Msg(key string) string {
	if trans, ok := translations[key]; ok {
		if val, ok := trans[l.Config.Code]; ok {
			return val
		}
	}
	return key
}

func (l *Locale) Msgf(key string, args ...interface{}) string {
	return fmt.Sprintf(l.Msg(key), args...)
}

func (l *Locale) Route(path string) string {
	if l.Config.Code == "LV" {
		return path
	}
	return "/" + l.Config.CodeLC + path
}

func (l *Locale) FormatDate(t time.Time, layout string) string {
	// Simple date formatting - can be enhanced with proper locale support
	monthNames := map[string][]string{
		"LV": {"janv.", "febr.", "marts", "apr.", "maijs", "jūn.", "jūl.", "aug.", "sept.", "okt.", "nov.", "dec."},
		"LT": {"saus.", "vas.", "koht.", "bal.", "geg.", "birž.", "liep.", "rugp.", "rugs.", "spal.", "lapkr.", "gruod."},
		"EE": {"jaan", "veebr", "märts", "apr", "mai", "juuni", "juuli", "aug", "sept", "okt", "nov", "dets"},
	}

	months := monthNames[l.Config.Code]
	monthIdx := int(t.Month()) - 1

	// Handle simple format like "d. MMM"
	if layout == "d. MMM" {
		return fmt.Sprintf("%d. %s", t.Day(), months[monthIdx])
	}

	return t.Format("02.01.2006 15:04")
}

func GetCountryConfigs() map[string]CountryConfig {
	return countryConfigs
}
