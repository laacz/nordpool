<?php

class PriceRepository
{
    private DateTimeZone $berlinTz;

    public function __construct(
        private PDO $db,
        private DateTimeZone $tz = new DateTimeZone('UTC'),
    ) {}

    /**
     * @return Price[]
     */
    public function getPrices(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $country = 'LV',
        int $resolution = 15,
    ): array {
        $sql = '
            SELECT *
            FROM price_indices
            WHERE country = :country
              AND ts_start >= :start
              AND ts_start < :end
              AND resolution_minutes = :resolution
            ORDER BY ts_start ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'country' => $country,
            'start' => $startDate->setTimezone($this->tz)->format('Y-m-d H:i:s'),
            'end' => $endDate->setTimezone($this->tz)->format('Y-m-d H:i:s'),
            'resolution' => $resolution,
        ]);

        return array_map(
            fn ($row) => new Price(
                price: (float) $row['value'] / 1000,
                startDate: new DateTimeImmutable($row['ts_start']),
                endDate: new DateTimeImmutable($row['ts_end']),
                country: $row['country'],
                resolution: (int) $row['resolution_minutes']
            ),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }
}
