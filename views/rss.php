<feed>
    <title type="text">Nordpool spot prices tomorrow (<?=$local_tomorrow_start->format('Y-m-d')?>)
        for <?=$country?></title>
    <updated><?=$current_time->format('Y-m-d\TH:i:sP')?></updated>
    <link rel="alternate" type="text/html" href="https://nordpool.didnt.work"/>
    <id>https://nordpool.didnt.work/feed</id>
    <?php foreach ($data as $price) {
        $ts_start = $price->startDate->setTimezone($tz_local);
        $ts_end = $price->endDate->setTimezone($tz_local);
        ?>
        <entry>
            <id><?=$country . '-' . $price->resolution . '-' . $ts_start->getTimestamp() . '-' . $ts_end->getTimestamp()?></id>
            <ts_start><?=$ts_start->format('Y-m-d\TH:i:sP')?></ts_start>
            <ts_end><?=$ts_end->format('Y-m-d\TH:i:sP')?></ts_end>
            <resolution><?=$price->resolution?></resolution>
            <price><?=htmlspecialchars($price->price)?></price>
            <price_vat><?=htmlspecialchars($price->price * (1 + $vat))?></price_vat>
        </entry>
    <?php } ?>
</feed>
