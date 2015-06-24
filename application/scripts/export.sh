#!/bin/bash

echo "START VIRTUOSO GRAPH EXPORT"

a=0
SESSION=$(date +%Y-%m-%d-%H:%m:%S)

if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi

if [ $# == 1 ]; then
    OWPATH=$1
fi

if [ ! -d "$OWPATH/dumps" ]; then
    mkdir $OWPATH/dumps >/dev/null 2>&1

fi

mkdir $OWPATH/dumps/export_$SESSION >/dev/null 2>&1
chmod -R 777 $OWPATH/dumps/export_$SESSION >/dev/null 2>&1

IFS=' '  read -r ISQL_PROG virt_user virt_pw <<< "`exec $OWPATH/application/scripts/virtuoso.sh $OWPATH`"

curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s a ?p}}" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sort >$OWPATH/dumps/export_$SESSION/graphs.lst

while read GRAPH
do
    GRAPH=$(echo $GRAPH | sed "s/\s*//g")

    if [ "$GRAPH" == "http://www.openlinksw.com/schemas/virtrdf#" ] || [ "$GRAPH" == "http://localhost:8890/DAV/" ] || [ "$GRAPH" == "http://localhost:8890/sparql" ] || [ "$GRAPH" == "http://www.w3.org/2002/07/owl#" ]; then
        echo "IGNORE $GRAPH";
    else
        # since default settings in virtuoso.ini restrict sparql queries to 10000 triples,. we count the triples of each graph before we start
        COUNT=$(curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT (COUNT(*) AS ?no) {GRAPH <$GRAPH> {?s ?p ?o }}" --data-urlencode "should-sponge=" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sed 's/[^0-9]*//g')
        a=$(($a+1))
        echo "EXPORT $GRAPH (containing $COUNT triples) to dumps/export_$SESSION/$a.ttl"
        b=0

        while true; do
            # in this loop queries with a limited resultset of max 10000 triples are fired
            OFFSET=$(($b*10000))
            curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=construct {?s ?p ?o} where {{ SELECT DISTINCT ?s ?p ?o FROM <$GRAPH> WHERE {?s ?p ?o .} ORDER BY ASC(?s)}} OFFSET $OFFSET LIMIT 10000" --data-urlencode "should-sponge=" --data-urlencode "format=text/plain">>$OWPATH/dumps/export_$SESSION/$a.ttl;
            echo $GRAPH > $OWPATH/dumps/export_$SESSION/$a.ttl.graph

            # if there are still more than 10000 triples to fetch, go on, otherwise skip to next graph
            if [ $COUNT -lt 10000 ]; then
                break;
            fi

            COUNT=$(($COUNT-10000));
            b=$(($b+1))
        done
    fi
done < $OWPATH/dumps/export_$SESSION/graphs.lst

echo "FINISHED VIRTUOSO GRAPH EXPORT"
echo "ALL DATA SAVED IN ./dumps/export_$SESSION/"
