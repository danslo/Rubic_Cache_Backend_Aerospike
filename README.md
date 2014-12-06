# Rubic_Cache_Backend_Aerospike

### What is this?

Magento Cache Backend for an in-memory NoSQL database called [Aerospike](http://www.aerospike.com/).

### Why?

- Aerospike is really easy to scale horizontally (as opposed to something like Redis Clustering).
- It's also really frickin' [fast](http://www.aerospike.com/performance/).
- Dat sexy [Aerospike Management Console](https://www.youtube.com/watch?v=CF83TmR-NME&t=3m0s).
- For fun and e-fame, yo.

### Installation

1. Install the [Aerospike PHP Client](http://www.aerospike.com/docs/client/php/install/).
2. Install this module using either [modman](https://github.com/colinmollenhour/modman) or [composer](https://getcomposer.org/).
3. Add something like the following to your Aerospike configuration (typically ``/etc/aerospike/aerospike.conf``):

    ```
    namespace magento {
        memory-size     2G
        default-ttl     0
        storage-engine  memory
    }
    ```
    
4. In ``app/etc/local.xml``, configure the Aerospike backend. Example:
```xml
<config>
    <global>
        ...
        <cache>
            <!-- The only mandatory setting. -->
            <backend>Rubic_Cache_Backend_Aerospike</backend>
            <backend_options>
                <!-- Optional, you can add multiple nodes here. -->
                <hosts>
                    <local>
                        <addr>127.0.0.1</addr>
                        <port>3000</port>
                    </local>
                </hosts>
                <!-- Also optional, make sure these correspond with your configuration. -->
                <namespace>magento</namespace>
                <set>cache</set>
            </backend_options>
        </cache>
        ...
    </global>
</config>
```

### Benchmarks

Soon.

### Known Limitations

No support for cleaning "old" records yet.

When doing operations on multiple records (such as cleaning by tags) we currently do filtering in PHP using UDFs instead of just using AQL queries. The reasons for this are;

- Aerospike only supports querying on indexes.
- Secondary indexes must be ints or strings.
- Tags are stored as arrays instead.

We should be able to overcome this limitation by storing tags in a different set, but I haven't gotten around to it yet. This does not impact the most important parts of the backend: reading and writing (single) cache records.

### License

Copyright 2014 Daniel Sloof

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License. You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.

