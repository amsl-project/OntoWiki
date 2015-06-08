#!/bin/bash
SESSION=$RANDOM
if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi
if [ $# == 1 ]; then
    OWPATH=$1
fi
mkdir /tmp/clean_$SESSION
chmod -R 777 /tmp/clean_$SESSION
curl -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s a ?p}}" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sort >/tmp/clean_$SESSION/graphs.lst

while read GRAPH
do
    GRAPH=$(echo $GRAPH | sed "s/\s*//g")
    if [ "$GRAPH" == "http://localhost/OntoWiki/Config/" ] || [ "$GRAPH" == "http://www.openlinksw.com/schemas/virtrdf#" ] || [ "$GRAPH" == "http://localhost:8890/DAV/" ] || [ "$GRAPH" == "http://localhost:8890/sparql" ] || [ "$GRAPH" == "http://www.w3.org/2002/07/owl#" ]; then
        echo "Überspringe Graph";
    else
        echo "Lösche $GRAPH" >> /tmp/clean_$SESSION/clean.log
        DELETE=$DELETE"SPARQL DROP GRAPH <$GRAPH>;"
    fi
done < /tmp/clean_$SESSION/graphs.lst
echo $OWPATH
IFS=' '  read -r ISQL_PROG virt_user virt_pw <<< "`exec $OWPATH/application/scripts/virtuoso.sh $OWPATH`"
echo "$ISQL_PROG -U $virt_user -P $virt_pw"
eval "$ISQL_PROG -U $virt_user -P $virt_pw exec=\"$DELETE\""
