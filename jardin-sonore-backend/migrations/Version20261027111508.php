<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027111508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache les adresses historiques aux communes sûres, préserve un secteur desservi non communal, puis supprime les champs redondants.';
    }

    public function up(Schema $schema): void
    {
        $unmatchedAddressCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM address_contact address
            WHERE address.municipality_id IS NULL
                AND (NULLIF(TRIM(address.city), '') IS NOT NULL OR NULLIF(TRIM(address.postal_code), '') IS NOT NULL)
                AND NOT (
                    (
                        (
                            UPPER(address.city) LIKE 'SAINT JEAN%BONNEFONDS'
                            OR UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) IN (
                                'SAINTPAULIEN', 'LEPUYENVELAY', 'VOREYSURARZON', 'LEMAZETSAINTVOY',
                                'SAINTMAURICESURLIGNON', 'SAINTFERREOLDAUROURE'
                            )
                        )
                        AND (
                            NULLIF(TRIM(address.postal_code), '') IS NULL
                            OR EXISTS (
                                SELECT 1 FROM municipality municipality
                                WHERE BINARY municipality.postal_code = BINARY address.postal_code
                                    AND BINARY municipality.insee_code = BINARY CASE
                                        WHEN UPPER(address.city) LIKE 'SAINT JEAN%BONNEFONDS' THEN '42237'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'SAINTPAULIEN' THEN '43216'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'LEPUYENVELAY' THEN '43157'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'VOREYSURARZON' THEN '43267'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'LEMAZETSAINTVOY' THEN '43130'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'SAINTMAURICESURLIGNON' THEN '43211'
                                        WHEN UPPER(REPLACE(REPLACE(TRIM(address.city), ' ', ''), CHAR(39), '')) = 'SAINTFERREOLDAUROURE' THEN '43184'
                                        ELSE NULL
                                    END
                            )
                        )
                    )
                    OR (
                        NULLIF(TRIM(address.city), '') IS NULL
                        AND
                        NULLIF(TRIM(address.postal_code), '') IS NOT NULL
                        AND 1 = (SELECT COUNT(*) FROM municipality municipality WHERE BINARY municipality.postal_code = BINARY address.postal_code)
                    )
                    OR (
                        NULLIF(TRIM(address.city), '') IS NOT NULL
                        AND NULLIF(TRIM(address.postal_code), '') IS NOT NULL
                        AND 1 = (
                            SELECT COUNT(*)
                            FROM municipality municipality
                            WHERE BINARY municipality.postal_code = BINARY address.postal_code
                                AND UPPER(TRIM(municipality.name)) = UPPER(TRIM(address.city))
                        )
                    )
                    OR (
                        UPPER(TRIM(address.city)) = 'OULLINS'
                        AND BINARY address.postal_code = BINARY '69600'
                        AND 1 = (
                            SELECT COUNT(*)
                            FROM municipality municipality
                            WHERE BINARY municipality.postal_code = BINARY address.postal_code
                                AND BINARY municipality.insee_code = BINARY '69149'
                        )
                    )
                    OR (
                        BINARY TRIM(address.city) = BINARY 'Pays du Gier (18 communes)'
                        AND NULLIF(TRIM(address.address), '') IS NULL
                        AND NULLIF(TRIM(address.label), '') IS NULL
                    )
                )
            SQL);

        $this->abortIf(
            0 < $unmatchedAddressCount,
            sprintf('%d address contacts still have legacy city/postal data that cannot be linked safely. Resolve them before dropping those columns.', $unmatchedAddressCount),
        );

        $this->addSql(<<<'SQL'
            UPDATE address_contact
            SET municipality_id = (
                SELECT municipality.id
                FROM municipality
                WHERE BINARY municipality.insee_code = BINARY CASE
                    WHEN UPPER(address_contact.city) LIKE 'SAINT JEAN%BONNEFONDS' THEN '42237'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTPAULIEN' THEN '43216'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'LEPUYENVELAY' THEN '43157'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'VOREYSURARZON' THEN '43267'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'LEMAZETSAINTVOY' THEN '43130'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTMAURICESURLIGNON' THEN '43211'
                    WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTFERREOLDAUROURE' THEN '43184'
                    ELSE NULL
                END
            )
            WHERE address_contact.municipality_id IS NULL
                AND address_contact.city IS NOT NULL
                AND (
                    NULLIF(TRIM(address_contact.postal_code), '') IS NULL
                    OR EXISTS (
                        SELECT 1 FROM municipality municipality
                        WHERE BINARY municipality.postal_code = BINARY address_contact.postal_code
                            AND BINARY municipality.insee_code = BINARY CASE
                                WHEN UPPER(address_contact.city) LIKE 'SAINT JEAN%BONNEFONDS' THEN '42237'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTPAULIEN' THEN '43216'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'LEPUYENVELAY' THEN '43157'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'VOREYSURARZON' THEN '43267'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'LEMAZETSAINTVOY' THEN '43130'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTMAURICESURLIGNON' THEN '43211'
                                WHEN UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) = 'SAINTFERREOLDAUROURE' THEN '43184'
                                ELSE NULL
                            END
                    )
                )
                AND (
                    UPPER(address_contact.city) LIKE 'SAINT JEAN%BONNEFONDS'
                    OR UPPER(REPLACE(REPLACE(TRIM(address_contact.city), ' ', ''), CHAR(39), '')) IN (
                        'SAINTPAULIEN', 'LEPUYENVELAY', 'VOREYSURARZON', 'LEMAZETSAINTVOY',
                        'SAINTMAURICESURLIGNON', 'SAINTFERREOLDAUROURE'
                    )
                )
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE address_contact address
            SET address.municipality_id = (
                SELECT MIN(municipality.id)
                FROM municipality municipality
                WHERE BINARY municipality.postal_code = BINARY address.postal_code
                    AND UPPER(TRIM(municipality.name)) = UPPER(TRIM(address.city))
                HAVING COUNT(*) = 1
            )
            WHERE address.municipality_id IS NULL
                AND NULLIF(TRIM(address.city), '') IS NOT NULL
                AND NULLIF(TRIM(address.postal_code), '') IS NOT NULL
                AND 1 = (
                    SELECT COUNT(*)
                    FROM municipality municipality
                    WHERE BINARY municipality.postal_code = BINARY address.postal_code
                        AND UPPER(TRIM(municipality.name)) = UPPER(TRIM(address.city))
                )
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE address_contact address
            SET address.municipality_id = (
                SELECT municipality.id
                FROM municipality municipality
                WHERE BINARY municipality.postal_code = BINARY '69600'
                    AND BINARY municipality.insee_code = BINARY '69149'
                LIMIT 1
            )
            WHERE address.municipality_id IS NULL
                AND UPPER(TRIM(address.city)) = 'OULLINS'
                AND BINARY address.postal_code = BINARY '69600'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE address_contact address
            SET address.label = 'Secteur desservi',
                address.address = address.city
            WHERE address.municipality_id IS NULL
                AND BINARY TRIM(address.city) = BINARY 'Pays du Gier (18 communes)'
                AND NULLIF(TRIM(address.address), '') IS NULL
                AND NULLIF(TRIM(address.label), '') IS NULL
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE address_contact address
            SET address.municipality_id = (
                SELECT MIN(municipality.id)
                FROM municipality municipality
                WHERE BINARY municipality.postal_code = BINARY address.postal_code
                HAVING COUNT(*) = 1
            )
            WHERE address.municipality_id IS NULL
                AND NULLIF(TRIM(address.city), '') IS NULL
                AND NULLIF(TRIM(address.postal_code), '') IS NOT NULL
            SQL);
        $this->addSql('DROP INDEX idx_address_contact_postal_code ON address_contact');
        $this->addSql('ALTER TABLE address_contact DROP postal_code, DROP city');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address_contact ADD postal_code VARCHAR(5) DEFAULT NULL, ADD city VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE address_contact INNER JOIN municipality ON municipality.id = address_contact.municipality_id SET address_contact.postal_code = municipality.postal_code, address_contact.city = municipality.name');
        $this->addSql('CREATE INDEX idx_address_contact_postal_code ON address_contact (postal_code)');
    }
}
