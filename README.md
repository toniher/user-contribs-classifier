# user-contribs-classifier

Tools for retrieving reports of users contributions

## User contributions

    php user-contribs.php myconfigfile.json MyUser taskname

    php user-contribs.php myconfigfile.json mylistuserfile taskname

## Hashtag contributions 

    php hashtag-count.php conf/viquiestirada.json viquiestirada

# TODO

- Document all options
- Counting type of contributions (how many images, references, etc.)
- Count using HTML for references, for instance
  `  https://en.wikipedia.org/w/api.php?action=parse&format=json&page=Dog&prop=text`
- Script for parsing CSV
- Lumen application

- Streaming process

  curl https://stream.wikimedia.org/v2/stream/revision-create?since=2020-02-06T00:00:00Z |grep 'wikidata.org' | sed 's/^data: //g' | grep 'Distributed Game'

Force a timeout:

https://superuser.com/questions/1493160/using-curl-to-download-a-web-stream
