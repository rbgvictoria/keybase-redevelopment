---
title: MELISR background updates
description: Describes the KeyBase organisational model
extends: _layouts.documentation
section: content
---

# MELISR background updates

## Scheduling

```bash
# m h  dom mon dow   command
*/2 8-18 * *   1-5   /home/niels/specify/database/update_storage.sh >/dev/null 2>&1
0   19 *   *   *     /home/niels/specify/database/daily.sh >/dev/null 2>&1

0   22 *   *   *     /data/backup/melisr/backup.sh >/dev/null 2>&1
```

```bash
#!/bin/bash
mysql -u admin -padmpwd -e "CALL proc_change_storage();" melisr
mysql -u admin -padmpwd -e "CALL proc_missing_storage();" melisr
mysql -u admin -padmpwd -e "CALL proc_change_storage_new_determination('00:02:01');" melisr

mysql -u admin -padmpwd -e "CALL proc_update_mixed_collection_string_interval('00:02:30')" melisr
mysql -u admin -padmpwd -e "CALL proc_update_type_status_string_interval('00:02:30')" melisr
mysql -u admin -padmpwd -e "CALL proc_update_verbatim_identification_interval('00:02:30')" melisr
mysql -u admin -padmpwd -e "CALL proc_update_dwc_coordinate_system_precision_interval('00:02:30')" melisr
```

## Procedures that run every other minute

### proc_change_storage

Changes `StorageID` on **Preparation** records if the storage (`Text3` or
`Text4`) on a **Taxon** record has changed. 

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_change_storage`()
BEGIN
    DECLARE var_preparation_id INTEGER(11);
	DECLARE var_storage_id INTEGER(11);

    DECLARE var_finished INT DEFAULT 0;
    DECLARE storage_cursor CURSOR FOR 
		select 
			p.PreparationID,
			if(d2.DeterminationID is null, s.StorageID, s2.StorageID) as StorageID
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.IsCurrent = true
		left join determination d2 on co.CollectionObjectID = d2.CollectionObjectID and d2.YesNo1 = true
		left join taxon t on d.TaxonID = t.TaxonID
		left join taxon t2 on substring_index(t.FullName, ' ', 1) = t2.FullName
		left join storage s on t2.text3 = s.FullName
		left join storage s2 on t2.text4 = s2.FullName
		where co.CollectionID = 4 and t.YesNo1 = true;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN storage_cursor;
    storage_loop: LOOP
        FETCH storage_cursor 
        INTO var_preparation_id, var_storage_id;

        UPDATE preparation
        SET StorageID = var_storage_id
        WHERE PreparationID = var_preparation_id;

        IF var_finished = 1 THEN
            LEAVE storage_loop;
        END IF;
    END LOOP storage_loop;
    CLOSE storage_cursor;
END
$$
DELIMITER ;
```

### proc_missing_storage

Sets `StorageID` on new **Preparation** records.

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_missing_storage`()
BEGIN
    DECLARE var_preparation_id INTEGER(11);
	DECLARE var_storage_id INTEGER(11);

    DECLARE var_finished INT DEFAULT 0;
    DECLARE storage_cursor CURSOR FOR 
		select 
			p.PreparationID,
			if(d2.DeterminationID is null, s.StorageID, s2.StorageID) as StorageID
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.IsCurrent = true
		left join determination d2 on co.CollectionObjectID = d2.CollectionObjectID and d2.YesNo1 = true
		left join taxon t on d.TaxonID = t.TaxonID
		left join taxon t2 on substring_index(t.FullName, ' ', 1) = t2.FullName
		left join storage s on t2.text3 = s.FullName
		left join storage s2 on t2.text4 = s2.FullName
		where co.CollectionID = 4 and p.StorageID is null and co.Modifier = 'A' and t.FullName is not null and t.RankID >= 180
		union
		select 
			p.PreparationID,
			if(d2.DeterminationID is null, s.StorageID, s2.StorageID) as StorageID
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.IsCurrent = true
		left join determination d2 on co.CollectionObjectID = d2.CollectionObjectID and d2.YesNo1 = true
		left join taxon t on d.TaxonID = t.TaxonID
		left join storage s on t.text3 = s.FullName
		left join storage s2 on t.text4 = s2.FullName
		where co.CollectionID = 4 and p.StorageID is null and co.Modifier = 'A' and t.FullName is not null and t.RankID < 180;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN storage_cursor;
    storage_loop: LOOP
        FETCH storage_cursor 
        INTO var_preparation_id, var_storage_id;

        UPDATE preparation
        SET StorageID = var_storage_id
        WHERE PreparationID = var_preparation_id;

        IF var_finished = 1 THEN
            LEAVE storage_loop;
        END IF;
    END LOOP storage_loop;
    CLOSE storage_cursor;
END
$$
DELIMITER ;
```

### proc_change_storage_new_determination

Changes the `StorageID` on **Preparation** records when there are new
determinations or nomenclatural type status designation.

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_change_storage_new_determination`(in_interval VARCHAR(16))
BEGIN
    DECLARE var_preparation_id INTEGER(11);
	DECLARE var_storage_id INTEGER(11);

    DECLARE var_finished INT DEFAULT 0;
    DECLARE storage_cursor CURSOR FOR 
		select 
			p.PreparationID,
			if(d2.DeterminationID is null, s.StorageID, s2.StorageID) as StorageID
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.IsCurrent = true
		left join determination d2 on co.CollectionObjectID = d2.CollectionObjectID and d2.YesNo1 = true
		left join taxon t on d.TaxonID = t.TaxonID
		left join taxon t2 on substring_index(t.FullName, ' ', 1) = t2.FullName
		left join storage s on t2.text3 = s.FullName
		left join storage s2 on t2.text4 = s2.FullName
		where co.CollectionID = 4 
			and d.TimestampModified > subtime(convert_tz(now(), 'system', 'Australia/Melbourne'), in_interval)
		union
		select 
			p.PreparationID,
			if(d2.DeterminationID is null, s.StorageID, s2.StorageID) as StorageID
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.IsCurrent = true
		left join determination d2 on co.CollectionObjectID = d2.CollectionObjectID and d2.YesNo1 = true
		left join taxon t on d.TaxonID = t.TaxonID
		left join taxon t2 on substring_index(t.FullName, ' ', 1) = t2.FullName
		left join storage s on t2.text3 = s.FullName
		left join storage s2 on t2.text4 = s2.FullName
		where co.CollectionID = 4 
			and d2.TimestampModified > subtime(convert_tz(now(), 'system', 'Australia/Melbourne'), in_interval);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN storage_cursor;
    storage_loop: LOOP
        FETCH storage_cursor 
        INTO var_preparation_id, var_storage_id;

        UPDATE preparation
        SET StorageID = var_storage_id
        WHERE PreparationID = var_preparation_id;

        IF var_finished = 1 THEN
            LEAVE storage_loop;
        END IF;
    END LOOP storage_loop;
    CLOSE storage_cursor;
END
$$
DELIMITER ;
```

### proc_update_mixed_collection_string_interval

Sets a 'mixedCollectionString' (`Text3`) cheat field in **Preparation**
table.

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_update_mixed_collection_string_interval`(in_interval VARCHAR(16))
BEGIN
    DECLARE var_preparation_id INTEGER(11);
	DECLARE var_mixed_collection_string TEXT;

    DECLARE var_finished INT DEFAULT 0;
    DECLARE co_cursor CURSOR FOR 
		SELECT p.PreparationID, fn_mixed_collection_string(p.CollectionObjectID) as MixedCollectionString
		FROM preparation p 
		JOIN collectionobject co ON p.CollectionObjectID = co.CollectionObjectID
		WHERE co.TimestampModified > subtime(CONVERT_TZ(NOW(), 'SYSTEM', 'Australia/Melbourne'), @in_interval)
		AND co.Modifier = 'A'
		AND co.AltCatalogNumber IN (
			SELECT AltCatalogNumber
			FROM collectionobject
			WHERE CollectionID = 4
			GROUP BY AltCatalogNumber 
			HAVING COUNT(*) > 1
		);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN co_cursor;
    co_loop: LOOP
        FETCH co_cursor 
        INTO var_preparation_id, var_mixed_collection_string;

        UPDATE preparation
        SET Text3 = var_mixed_collection_string
        WHERE PreparationID = var_preparation_id;

        IF var_finished = 1 THEN
            LEAVE co_loop;
        END IF;
    END LOOP co_loop;
    CLOSE co_cursor;
END
$$
DELIMITER;
```

### proc_update_type_status_string_interval

Sets 'typeStatusString'(`Text4`) and 'typeStatusProtologueString' (`Text5`)
cheat fields in **Preparation** table. 

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_update_type_status_string_interval`(in_interval VARCHAR(16))
BEGIN
    DECLARE var_preparation_id INTEGER(11);
	DECLARE var_type_status_string TEXT;
	DECLARE var_type_status_protologue_string TEXT;

    DECLARE var_finished INT DEFAULT 0;
    DECLARE preparation_cursor CURSOR FOR 
		select p.PreparationID, fn_type_status_string(p.CollectionObjectID), fn_type_status_protologue_string(p.CollectionObjectID)
		from preparation p
		join collectionobject co on p.CollectionObjectID = co.CollectionObjectID
		left join determination d on co.CollectionObjectID = d.CollectionObjectID and d.YesNo1 = true
		where co.TimestampModified > subtime(convert_tz(now(), 'system', 'Australia/Melbourne'), in_interval);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN preparation_cursor;
    preparation_loop: LOOP
        FETCH preparation_cursor 
        INTO var_preparation_id, var_type_status_string, var_type_status_protologue_string;

        UPDATE preparation
        SET Text4 = var_type_status_string,
        	Text5 = var_type_status_protologue_string
        WHERE PreparationID = var_preparation_id;

        IF var_finished = 1 THEN
            LEAVE preparation_loop;
        END IF;
    END LOOP preparation_loop;
    CLOSE preparation_cursor;
END
$$
DELIMITER ;
```

### proc_update_verbatim_identification_interval

Sets an `dwc:verbatimIdentification` cheat field (`Text1`) for **Determinations**
that have an identification qualifier.

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_update_verbatim_identification_interval`(in_interval varchar(16))
BEGIN
    DECLARE var_determination_id INTEGER(11);
	DECLARE var_verbatim_identification TEXT;
	
    DECLARE var_finished INT DEFAULT 0;
    DECLARE determination_cursor CURSOR FOR 
		select d.DeterminationID, if(d.Qualifier is not null, fn_verbatim_identification(d.DeterminationID), null)
		from determination d 
		where d.TimestampModified > subtime(convert_tz(now(), 'system', 'Australia/Melbourne'), in_interval);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

    OPEN determination_cursor;
    determination_loop: LOOP
        FETCH determination_cursor 
        INTO var_determination_id, var_verbatim_identification;

        UPDATE determination
        SET Text1 = var_verbatim_identification
        WHERE DeterminationID = var_determination_id;

        IF var_finished = 1 THEN
            LEAVE determination_loop;
        END IF;
    END LOOP determination_loop;
    CLOSE determination_cursor;
END
$$
DELIMITER ;
```

### proc_update_dwc_coordinate_system_precision_interval

Sets `dwc:verbatimCoordinateSystem` (`OriginalCoordSystem`) and
`dwc:coodinatePrecision` (`Number1`) in **GeoCoordDetail** table.

```sql
DELIMITER $$
$$
CREATE PROCEDURE `proc_update_dwc_coordinate_system_precision_interval`(in_interval VARCHAR(16))
BEGIN
	DECLARE var_locality_id INTEGER(11);
	DECLARE var_geocoord_detail_id INTEGER(11);
	DECLARE var_dwc_verbatim_coordinate_system VARCHAR(128);
	DECLARE var_dwc_coordinate_precision DECIMAL(20, 10);

    DECLARE var_finished INT DEFAULT 0;
    DECLARE locality_cursor CURSOR FOR 
		SELECT 
            l.LocalityID, 
            gc.GeoCoordDetailID, 
            dwc_verbatim_coordinate_system(l.Lat1Text, l.Long1Text), 
            dwc_coordinate_precision(l.Lat1Text, l.Long1Text)
		FROM locality l
		LEFT JOIN geocoorddetail gc ON l.LocalityID = gc.LocalityID 
		WHERE l.Lat1Text IS NOT NULL AND l.Long1Text IS NOT NULL 
            AND l.Lat1Text != '' AND l.Long1Text != ''
    		AND coalesce(l.TimestampModified) > subtime(CONVERT_TZ(NOW(), 'SYSTEM', 'Australia/Melbourne'), in_interval);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET var_finished = 1;

	OPEN locality_cursor;
	locality_loop: LOOP
		FETCH locality_cursor
		INTO var_locality_id, var_geocoord_detail_id, var_dwc_verbatim_coordinate_system, var_dwc_coordinate_precision;

		IF var_geocoord_detail_id IS NULL THEN
			INSERT INTO geocoorddetail (TimestampCreated, TimestampModified, Version, OriginalCoordSystem, Number1, CreatedByAgentID, LocalityID)
			VALUES (
				now(), 
				now(), 
				1, 
				var_dwc_verbatim_coordinate_system, 
				var_dwc_coordinate_precision, 
				1, 
				var_locality_id
			);
		ELSE
			UPDATE geocoorddetail 
			SET OriginalCoordSystem = var_dwc_verbatim_coordinate_system,
				Number1 = var_dwc_coordinate_precision,
				TimestampModified = now(),
				Version = Version + 1,
				ModifiedByAgentID = 1
			WHERE GeoCoordDetailID = var_geocoord_detail_id;
		
		END IF;
	
		IF var_finished = 1 THEN
            LEAVE locality_loop;
        END IF;
	END LOOP locality_loop;
	CLOSE locality_cursor;
END
$$
DELIMITER ;
```

## Functions

### fn_mixed_collection_string

Creates mixed collection string

```sql
DELIMITER $$
$$
CREATE FUNCTION `fn_mixed_collection_string`(in_collection_object_id INT) 
RETURNS TEXT 
BEGIN
	DECLARE out_mixed_collection_string TEXT DEFAULT null;

	SELECT mix.mixed_collection_string
	INTO out_mixed_collection_string
	FROM collectionobject co
	JOIN (
		SELECT co.AltCatalogNumber,
			GROUP_CONCAT(CONCAT(co.Modifier, '. ', 
				CONCAT(IF(ttdi.RankID >= 180, '<i>', ''), coalesce(d.Text1, t.FullName), IF(ttdi.RankID >= 180, '</i>', ''), 
        IF(t.Author IS NOT NULL, CONCAT(' ', t.author), ''), IF(co.ObjectCondition IS NOT NULL, 
				CONCAT(' – ', co.ObjectCondition), ''))) ORDER BY co.Modifier SEPARATOR ' | ') as mixed_collection_string
		FROM collectionobject co
		JOIN determination d ON co.CollectionObjectID = d.CollectionObjectID 
		JOIN taxon t ON d.TaxonID = t.TaxonID
		JOIN taxontreedefitem ttdi ON t.TaxonTreeDefItemID = ttdi.TaxonTreeDefItemID 
		WHERE co.CollectionMemberID = 4 AND d.IsCurrent = true
		GROUP BY co.AltCatalogNumber 
		HAVING COUNT(*) > 1) AS mix ON co.AltCatalogNumber = mix.AltCatalogNumber
	WHERE co.Modifier = 'A' AND co.CollectionObjectID = in_collection_object_id;

	RETURN out_mixed_collection_string;
END
$$
DELIMITER ;
```

### fn_type_status_string

Creates `type status` string.

```sql
DELIMITER $$
$$
CREATE FUNCTION `fn_type_status_string`(in_collection_object_id INT) 
RETURNS VARCHAR(255)
BEGIN
	DECLARE out_type_status_string VARCHAR(255) DEFAULT NULL;
	DECLARE var_type_of_type VARCHAR(32) DEFAULT NULL;
	DECLARE var_qualifier VARCHAR(16) DEFAULT NULL;
	DECLARE var_taxon_name VARCHAR(128) DEFAULT NULL;
	DECLARE var_taxon_name_author VARCHAR(128) DEFAULT NULL;

	select d.TypeStatusName, d.SubspQualifier, t.FullName, t.Author
	into var_type_of_type, var_qualifier, var_taxon_name, var_taxon_name_author
	from collectionobject co
	join determination d on co.CollectionObjectID = d.CollectionObjectID 
	join taxon t on d.TaxonID = t.TaxonID
	where co.CollectionObjectID = in_collection_object_id
		and co.CollectionMemberID = 4 
		and d.YesNo1 = true
	limit 1;
	
	if var_taxon_name is not null then
		if (var_qualifier) is null then
			SET out_type_status_string = concat('<b>', UPPER(var_type_of_type), '</b>', ' of ');
		else 
			SET out_type_status_string = concat(concat(upper(left(var_qualifier,1)), substring(var_qualifier, 2)), if(var_qualifier='?', '', ' ') ,'<b>', UPPER(var_type_of_type), '</b>', ' of ');
		end if;
	
		SET var_taxon_name = CONCAT('<i>', REPLACE(
			REPLACE(
				REPLACE(
					REPLACE(var_taxon_name, ' subsp. ', '</i> subsp. <i>'), 
						' var. ', '</i> var. <i>'
					), ' subvar. ', '</i> subvar. <i>'
				), ' f. ', '</i> f. <i>'
			), '</i>'
		);
		SET out_type_status_string = concat(out_type_status_string, var_taxon_name, ' ', var_taxon_name_author);
	end if;

	return out_type_status_string;
END
$$
DELIMITER ;
```

### fn_type_status_protologue_string

Creates `type status protologue` string.

```sql
DELIMITER $$
$$
CREATE FUNCTION `fn_type_status_protologue_string`(in_collection_object_id INT) 
RETURNS VARCHAR(255)
BEGIN
	DECLARE out_type_status_protologue_string VARCHAR(255) DEFAULT NULL;
	DECLARE var_taxon_name VARCHAR(128) DEFAULT NULL;
	DECLARE var_protologue VARCHAR(128) DEFAULT NULL;
	DECLARE var_year VARCHAR(128) DEFAULT NULL;

	select t.FullName, t.CommonName, t.Number2 
	into var_taxon_name, var_protologue, var_year
	from collectionobject co
	join determination d on co.CollectionObjectID = d.CollectionObjectID 
	join taxon t on d.TaxonID = t.TaxonID
	where co.CollectionObjectID = in_collection_object_id
		and co.CollectionMemberID = 4 
		and d.YesNo1 = true
	limit 1;
	
	if var_taxon_name is not null then
		SET out_type_status_protologue_string = concat(var_protologue, ' (', var_year, ')');
	end if;

	return out_type_status_protologue_string;
END
$$
DELIMITER ;
```

### fn_verbatim_identification

Creates `dwc:verbatimIdentification` string.

```sql
DELIMITER $$
$$
CREATE FUNCTION `fn_verbatim_identification`(in_determination_id integer) 
RETURNS varchar(255)
BEGIN
	DECLARE var_taxon_name VARCHAR(255);
	DECLARE var_qualifier VARCHAR(16) DEFAULT NULL;
	DECLARE var_qualifier_rank VARCHAR(16) DEFAULT NULL;
	DECLARE var_name_rank_id INTEGER DEFAULT NULL;
	DECLARE out_str VARCHAR(255) DEFAULT NULL;

	SELECT d.Qualifier, d.VarQualifier, t.FullName, ttdi.RankID 
	INTO var_qualifier, var_qualifier_rank, var_taxon_name, var_name_rank_id
	FROM determination d 
	JOIN taxon t ON d.TaxonID = t.TaxonID 
	JOIN taxontreedefitem ttdi ON t.TaxonTreeDefItemID = ttdi.TaxonTreeDefItemID 
	WHERE d.DeterminationID = in_determination_id AND d.Qualifier IS NOT NULL
	LIMIT 1;
	
	IF var_qualifier IS NOT NULL THEN
		IF var_qualifier != '?' THEN
			SET var_qualifier = CONCAT(var_qualifier, ' ');
		END IF;
	
		IF var_name_rank_id < 220 THEN
			SET out_str = CONCAT(var_qualifier, var_taxon_name);
		ELSEIF var_name_rank_id = 220 THEN
			IF var_qualifier_rank = 'genus' THEN
				SET out_str = CONCAT(var_qualifier, var_taxon_name);
			ELSE
				SET out_str = CONCAT(SUBSTRING_INDEX(var_taxon_name, ' ', 1), ' ', 
                    var_qualifier, SUBSTRING(var_taxon_name, 
                    LENGTH(SUBSTRING_INDEX(var_taxon_name, ' ', 1)) + 2));
			END IF;
		ELSE 
			IF var_qualifier_rank = 'genus' THEN
				SET out_str = CONCAT(var_qualifier, var_taxon_name);
			ELSEIF var_qualifier_rank = 'species' THEN
				SET out_str = CONCAT(SUBSTRING_INDEX(var_taxon_name, ' ', 1), ' ', 
                    var_qualifier, SUBSTRING(var_taxon_name, 
                    LENGTH(SUBSTRING_INDEX(var_taxon_name, ' ', 1)) + 2));
			ELSE 
				SET out_str = CONCAT(SUBSTRING_INDEX(var_taxon_name, ' ', 2), ' ', 
                    var_qualifier, SUBSTRING(var_taxon_name, 
                    LENGTH(SUBSTRING_INDEX(var_taxon_name, ' ', 2)) + 2));
			END IF;
		END IF;
	END IF;

	RETURN out_str;
END
$$
DELIMITER ;
```

### dwc_verbatim_coordinate_system

Creates `dwc:verbatimCoordinateSystem` string.

```sql
DELIMITER $$
$$
CREATE FUNCTION `dwc_verbatim_coordinate_system`(in_lat VARCHAR(32), in_lng VARCHAR(32)) 
RETURNS varchar(32)
BEGIN
	DECLARE out_verbatim_coordinate_system VARCHAR(32);

	CASE
		WHEN LENGTH(in_lat)-LENGTH(REPLACE(in_lat, ' ', '')) = 3 OR LENGTH(in_lng)-LENGTH(REPLACE(in_lng, ' ', '')) = 3 THEN
			SET out_verbatim_coordinate_system='degrees minutes seconds';
		WHEN LENGTH(in_lat)-LENGTH(REPLACE(in_lat, ' ', '')) = 2 OR LENGTH(in_lng)-LENGTH(REPLACE(in_lng, ' ', '')) = 2 THEN
			SET out_verbatim_coordinate_system='degrees decimal minutes';
		WHEN in_lat IS NOT NULL AND in_lng IS NOT NULL THEN
			SET out_verbatim_coordinate_system='decimal degrees';
		ELSE SET out_verbatim_coordinate_system=NULL;
	END CASE;
	
	RETURN out_verbatim_coordinate_system;
			
END
$$
DELIMITER ;
```

### dwc_coordinate_precision

Calculate `dwc:coordinatePrecision`.

```sql
DELIMITER $$
$$
CREATE FUNCTION `dwc_coordinate_precision`(in_lat varchar(32), in_lng varchar(32)) 
RETURNS decimal(11,10)
BEGIN
	
	DECLARE out_coordinate_precision DECIMAL(11,10);

	DECLARE var_verbatim_coordinate_system VARCHAR(32);

	DECLARE var_lat_degrees VARCHAR(32);
	DECLARE var_lat_minutes VARCHAR(32);
	DECLARE var_lat_seconds VARCHAR(32);
	DECLARE var_lat_factor SMALLINT;
	DECLARE var_lng_degrees VARCHAR(32);
	DECLARE var_lng_minutes VARCHAR(32);
	DECLARE var_lng_seconds VARCHAR(32);
	DECLARE var_lng_factor SMALLINT;

	SET var_verbatim_coordinate_system = dwc_verbatim_coordinate_system(in_lat, in_lng);

	CASE var_verbatim_coordinate_system
		WHEN 'degrees minutes seconds' THEN
			SET var_lat_seconds = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '"', '');
			IF var_lat_seconds NOT LIKE '%.%' OR var_lat_seconds LIKE '%°%' OR var_lat_seconds LIKE '%''&' THEN
				SET var_lat_factor = 0;
			ELSE
				SET var_lat_factor = LENGTH(SUBSTRING(var_lat_seconds, LOCATE('.', var_lat_seconds)+1)); 
			END IF;

			SET var_lng_seconds = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '"', '');
			IF var_lng_seconds NOT LIKE '%.%' OR var_lng_seconds LIKE '%°%' OR var_lng_seconds LIKE '%''&' THEN
				SET var_lng_factor = 0;
			ELSE
				SET var_lng_factor = LENGTH(SUBSTRING(var_lng_seconds, LOCATE('.', var_lng_seconds)+1)); 
			END IF;
		
			IF var_lat_factor >= var_lng_factor THEN
				SET out_coordinate_precision = 0.00027777778 * POWER(10, 0 - var_lat_factor);
			ELSE
				SET out_coordinate_precision = 0.00027777778 * POWER(10, 0 - var_lng_factor);
			END IF;
		
		WHEN 'degrees decimal minutes' THEN

			SET var_lat_minutes = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '''', '');
			IF var_lat_minutes NOT LIKE '%.%' OR var_lat_minutes LIKE '%°%' OR var_lat_minutes LIKE '%''&' THEN
				SET var_lat_factor = 0;
			ELSE
				SET var_lat_factor = LENGTH(SUBSTRING(var_lat_minutes, LOCATE('.', var_lat_minutes)+1)); 
			END IF;

			SET var_lng_minutes = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '''', '');
			IF var_lng_minutes NOT LIKE '%.%' OR var_lng_minutes LIKE '%°%' THEN
				SET var_lng_factor = 0;
			ELSE
				SET var_lng_factor = LENGTH(SUBSTRING(var_lng_minutes, LOCATE('.', var_lng_minutes)+1)); 
			END IF;
		
			IF var_lat_factor >= var_lng_factor THEN
				SET out_coordinate_precision = 0.01666666667 * POWER(10, 0 - var_lat_factor);
			ELSE
				SET out_coordinate_precision = 0.01666666667 * POWER(10, 0 - var_lng_factor);
			END IF;

		WHEN 'decimal degrees' THEN
		
			SET var_lat_degrees = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '°', '');
			IF var_lat_degrees NOT LIKE '%.%' OR var_lat_degrees LIKE '%°%' OR var_lat_degrees LIKE '%''&' THEN
				SET var_lat_factor = 0;
			ELSE
				SET var_lat_factor = LENGTH(SUBSTRING(var_lat_degrees, LOCATE('.', var_lat_degrees)+1)); 
			END IF;

			SET var_lng_degrees = REPLACE(SUBSTRING(SUBSTRING_INDEX(in_lat, ' ', -2), 1, LOCATE(' ', SUBSTRING_INDEX(in_lat, ' ', -2))-1), '°', '');
			IF var_lng_degrees NOT LIKE '%.%' THEN
				SET var_lng_factor = 0;
			ELSE
				SET var_lng_factor = LENGTH(SUBSTRING(var_lng_degrees, LOCATE('.', var_lng_degrees)+1)); 
			END IF;
		
			IF var_lat_factor >= var_lng_factor THEN
				SET out_coordinate_precision = POWER(10, 0 - var_lat_factor);
			ELSE
				SET out_coordinate_precision = POWER(10, 0 - var_lng_factor);
			END IF;
		
		ELSE
		
			SET out_coordinate_precision = NULL;
		
	END CASE;

	RETURN out_coordinate_precision;
END
$$
DELIMITER ;
```

