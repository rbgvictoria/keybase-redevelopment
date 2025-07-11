---
title: Turtle example
description: Example in TurTLe language
extends: _layouts.documentation
section: content
---

# Turtle example

```turtle
@prefix tcs: <http://rs.tdwg.org/tcs/terms/> .
@prefix dcterms: <http://purl.org/dc/terms/> .
@prefix dwc: <http://rs.tdwg.org/dwc/terms/> .
@prefix bibo: <http://purl.org/ontology/bibo/> .

[] a tcs:TaxonConcept ;
    dcterms:title "Dicranoloma blumei sec. Klazenga 1999" ;
    tcs:accordingTo <https://www.tropicos.org/reference/9020903> 
    tcs:taxonName <https://www.tropicos.org/name/35121475> .

<https://www.tropicos.org/name/35121475> a tcs:TaxonName ;
    tcs:nameString "Dicranoloma blumei" ;
    dwc:scientificNameAuthorship "(Nees) Renauld" .

<https://www.tropicos.org/reference/9020903> a bibo:AcademicArticle ;
    dcterms:bibliographicCitation """Klazenga, N. (1999). A revision of the 
            Malesian species of Dicranoloma (Dicranaceae, Musci). Journal of the 
            Hattori Botanical Laboratory 87: 1-130.""" .
```

```ttl
@prefix tcs: <http://rs.tdwg.org/tcs/terms/> .
@prefix dcterms: <http://purl.org/dc/terms/> .
@prefix dwc: <http://rs.tdwg.org/dwc/terms/> .
@prefix dsw: <http://purl.org/dsw/> .
@prefix dwciri: <http://rs.tdwg.org/dwc/iri/> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix oa: <http://www.w3.org/ns/oa#> .

<https://specify.rbg.vic.gov.au/specify/view/collectionobject/273521> a dwc:PreservedSpecimen ;
    dsw:evidenceFor <https://avh.ala.org.au/occurrences/89086162-0bd8-4504-8abd-960d0a97d41a> ;
    dsw:isBasisForId _:b0 ;
    dwciri:typeStatus [ a tcs:NomenclaturalType ;
            tcs:typeOfType <http://rs.gbif.org/vocabulary/gbif/type_status/isolectotype> ;
            tcs:typifiedName <https://ipni.org/n/703183-1> ;
            tcs:typeSpecimen <https://specify.rbg.vic.gov.au/specify/view/collectionobject/273521> ] .

[] a oa:Annotation ;
    oa:motivatedBy oa:commenting ;
    oa:hasBody _:b0 ;
    oa:hasTarget <https://specify.rbg.vic.gov.au/specify/view/collectionobject/273521> ;
    dcterms:creator <http://www.wikidata.org/entity/Q6135471> ;
    dcterms:created "1942-02-25" .

_:b0 a dwc:Identification ;
    dwciri:toTaxon <https://id.biodiversity.org.au/instance/apni/601693> ;
    dwciri:identifiedBy <http://www.wikidata.org/entity/Q6135471> ;
    dwc:dateIdentified "1942-02-25" .

<https://id.biodiversity.org.au/instance/apni/601693> a tcs:TaxonConcept ;
    dcterms:title "Banksia serrata sec. Australian Plant Census 2005" ;
    tcs:taxonName <https://ipni.org/n/703183-1> ;
    tcs:accordingTo <https://id.biodiversity.org.au/reference/apni/42312> .

<https://ipni.org/n/703183-1> a tcs:TaxonName ;
    tcs:nameString "Banksia serrata" ;
    dwc:scientificNameAuthorship "L.f." ;
    dwc:namePublishedIn "Suppl. Pl. 126" ;
    dwc:namePublishedInYear "1782" .

<https://avh.ala.org.au/occurrences/89086162-0bd8-4504-8abd-960d0a97d41a> a dwc:Occurrence ;
    dwciri:recordedBy <http://www.wikidata.org/entity/Q153408> ,
            <http://www.wikidata.org/entity/Q39789> ;
    dsw:atEvent [ a dwc:Event ;
            dwc:eventDate "1770-04" ;
            dwciri:inDescribedPlace [ a dcterms:Location ;
                    dwc:country "Australia" ;
                    dwc:stateOrProvince "New South Wales" ;
                    dwc:verbatimLocality "Botany Bay" ;
                    dwc:verbatimLatitude "33° 58' S" ;
                    dwc:verbatimLongitude "151° 12' E" ;
                    dwc:decimalLatitude -33.96670 ;
                    dwc:decimalLongitude 151.20000 ;
                    dwc:coordinatePrecision 0.016667 ;
                    dwc:coordinateUncertaintyInMeters 10000 ;
                    dwciri:geodeticDatum <https://epsg.io/4326> ] ] ;
    dsw:hasEvidence <https://specify.rbg.vic.gov.au/specify/view/collectionobject/273521> .

<http://www.wikidata.org/entity/Q6135471> a foaf:Person ;
    foaf:givenName "James Hamlyn" ;
    foaf:surname "Willis" .

<http://www.wikidata.org/entity/Q153408> a foaf:Person ;
    foaf:givenName "Joseph" ;
    foaf:surname "Banks" .

<http://www.wikidata.org/entity/Q39789> a foaf:Person ;
    foaf:givenName "Daniel Carl" ;
    foaf:surname "Solander" .
```

