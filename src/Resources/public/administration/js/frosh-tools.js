(()=>{var{ApiService:i}=Shopware.Classes,c=class extends i{constructor(e,t,a="_action/frosh-tools"){super(e,t,a)}getCacheInfo(){let e=`${this.getApiBasePath()}/cache`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}clearCache(e){let t=`${this.getApiBasePath()}/cache/${e}`;return this.httpClient.delete(t,{headers:this.getBasicHeaders()}).then(a=>i.handleResponse(a))}getQueue(){let e=`${this.getApiBasePath()}/queue/list`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}resetQueue(){let e=`${this.getApiBasePath()}/queue`;return this.httpClient.delete(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}runScheduledTask(e){let t=`${this.getApiBasePath()}/scheduled-task/${e}`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(a=>i.handleResponse(a))}scheduledTasksRegister(){let e=`${this.getApiBasePath()}/scheduled-tasks/register`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}healthStatus(){let e=`${this.getApiBasePath()}/health/status`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}performanceStatus(){let e=`${this.getApiBasePath()}/performance/status`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}getLogFiles(){let e=`${this.getApiBasePath()}/logs/files`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>i.handleResponse(t))}getLogFile(e,t=0,a=20){let n=`${this.getApiBasePath()}/logs/file`;return this.httpClient.get(n,{params:{file:e,offset:t,limit:a},headers:this.getBasicHeaders()}).then(r=>r)}getShopwareFiles(){let e=`${this.getApiBasePath()}/shopware-files`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>t)}getFileContents(e){let t=`${this.getApiBasePath()}/file-contents`;return this.httpClient.get(t,{params:{file:e},headers:this.getBasicHeaders()}).then(a=>a)}restoreShopwareFile(e){let t=`${this.getApiBasePath()}/shopware-file/restore`;return this.httpClient.get(t,{params:{file:e},headers:this.getBasicHeaders()}).then(a=>a)}stateMachines(e){let t=`${this.getApiBasePath()}/state-machines/load`;return this.httpClient.get(t,{params:{stateMachine:e},headers:this.getBasicHeaders()}).then(a=>i.handleResponse(a))}},p=c;var{ApiService:o}=Shopware.Classes,h=class extends o{constructor(e,t,a="_action/frosh-tools/elasticsearch"){super(e,t,a)}status(){let e=`${this.getApiBasePath()}/status`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}indices(){let e=`${this.getApiBasePath()}/indices`;return this.httpClient.get(e,{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}deleteIndex(e){let t=`${this.getApiBasePath()}/index/`+e;return this.httpClient.delete(t,{headers:this.getBasicHeaders()}).then(a=>o.handleResponse(a))}console(e,t,a){let n=`${this.getApiBasePath()}/console`+t;return this.httpClient.request({url:n,method:e,headers:{...this.getBasicHeaders(),"content-type":"application/json"},data:a}).then(r=>o.handleResponse(r))}flushAll(){let e=`${this.getApiBasePath()}/flush_all`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}reindex(){let e=`${this.getApiBasePath()}/reindex`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}switchAlias(){let e=`${this.getApiBasePath()}/switch_alias`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}cleanup(){let e=`${this.getApiBasePath()}/cleanup`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}reset(){let e=`${this.getApiBasePath()}/reset`;return this.httpClient.post(e,{},{headers:this.getBasicHeaders()}).then(t=>o.handleResponse(t))}},m=h;var{Application:l}=Shopware;l.addServiceProvider("froshToolsService",s=>{let e=l.getContainer("init");return new p(e.httpClient,s.loginService)});l.addServiceProvider("froshElasticSearch",s=>{let e=l.getContainer("init");return new m(e.httpClient,s.loginService)});var u=`{% block sw_data_grid_inline_edit_type_unknown %}
    <sw-datepicker
        v-else-if="column.inlineEdit === 'date'"
        dateType="date"
        v-model="currentValue">
    </sw-datepicker>

    <sw-datepicker
        v-else-if="column.inlineEdit === 'datetime'"
        dateType="datetime"
        v-model="currentValue">
    </sw-datepicker>

    {% parent() %}
{% endblock %}
`;var{Component:_}=Shopware;_.override("sw-data-grid-inline-edit",{template:u});var f=`{% block sw_version_status %}
    <router-link
        v-if="hasPermission"
        :to="{ name: 'frosh.tools.index.index' }"
        class="sw-version__status has-permission"
        v-tooltip="{
            showDelay: 300,
            message: healthPlaceholder
        }"
    >
        {% block sw_version_status_badge %}
            <sw-color-badge v-if="health && hasPermission" :variant="healthVariant" :rounded="true"></sw-color-badge>
            <template  v-else>
                {% parent %}
            </template>
        {% endblock %}
    </router-link>
    <template  v-else>
        {% parent %}
    </template>
{% endblock %}
`;var{Component:k}=Shopware;k.override("sw-version",{template:f,inject:["froshToolsService","acl"],async created(){this.checkPermission()&&await this.checkHealth()},data(){return{health:null,hasPermission:!1}},computed:{healthVariant(){let s="success";for(let e of this.health){if(e.state==="STATE_ERROR"){s="error";continue}e.state==="STATE_WARNING"&&s==="success"&&(s="warning")}return s},healthPlaceholder(){let s="Shop Status: Ok";if(this.health===null)return s;for(let e of this.health){if(e.state==="STATE_ERROR"){s="Shop Status: May outage, Check System Status";continue}e.state==="STATE_WARNING"&&s==="Shop Status: Ok"&&(s="Shop Status: Issues, Check System Status")}return s}},methods:{async checkHealth(){this.health=await this.froshToolsService.healthStatus(),setInterval(async()=>{this.health=await this.froshToolsService.healthStatus()},3e4)},checkPermission(){return this.hasPermission=this.acl.can("frosh_tools:read")}}});var g=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-index__health-card"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-index"
    >

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh">
                <sw-icon :small="true" name="regular-undo"></sw-icon>
            </sw-button>
        </template>

        <sw-card
                class="frosh-tools-tab-index__health-card"
                :title="$tc('frosh-tools.tabs.index.title')"
                :large="true"
                positionIdentifier="frosh-tools-tab-index-health"
        >
            <sw-data-grid
                v-if="health"
                :showSelection="false"
                :showActions="false"
                :dataSource="health"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ item.snippet }}</a>
                    </template>
                    <template v-else>{{ item.snippet }}</template>
                </template>
            </sw-data-grid>
        </sw-card>

        <sw-card
                class="frosh-tools-tab-index__performance-card"
                :title="$tc('frosh-tools.tabs.index.performance')"
                :large="true"
                positionIdentifier="frosh-tools-tab-index-performance"
        >
            <sw-data-grid
                v-if="performanceStatus"
                :showSelection="false"
                :showActions="false"
                :dataSource="performanceStatus"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ item.snippet }}</a>
                    </template>
                    <template v-else>{{ item.snippet }}</template>
                </template>
            </sw-data-grid>
        </sw-card>
    </sw-card>
</sw-card-view>
`;var{Component:A}=Shopware;A.register("frosh-tools-tab-index",{inject:["froshToolsService"],template:g,data(){return{isLoading:!0,health:null,performanceStatus:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"current",label:"frosh-tools.current",rawData:!0},{property:"recommended",label:"frosh-tools.recommended",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.health=await this.froshToolsService.healthStatus(),this.performanceStatus=await this.froshToolsService.performanceStatus(),this.isLoading=!1}}});var w=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-cache__cache-card"
            :title="$tc('frosh-tools.tabs.cache.title')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-cache"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="regular-undo"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="cacheFolders"
            :columns="columns"
        >

            <template #column-name="{ item }">
                <sw-label variant="success" appearance="pill" v-if="item.active" >
                    {{ $tc('frosh-tools.active') }}
                </sw-label>
                <sw-label variant="primary" appearance="pill" v-if="item.type" >
                    {{ item.type }}
                </sw-label>
                {{ item.name }}
            </template>

            <template #column-size="{ item }">
                <template v-if="item.size < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.size) }}
                </template>
            </template>

            <template #column-freeSpace="{ item }">
                <template v-if="item.freeSpace < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.freeSpace) }}
                </template>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="clearCache(item)">
                    {{ $tc('frosh-tools.clear') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card
            class="frosh-tools-tab-cache__action-card"
            :title="$tc('frosh-tools.actions')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-cache-action"
    >
        <sw-button variant="primary" @click="compileTheme">{{ $tc('frosh-tools.compileTheme') }}</sw-button>
    </sw-card>
</sw-card-view>
`;var{Component:B,Mixin:I}=Shopware,{Criteria:P}=Shopware.Data;B.register("frosh-tools-tab-cache",{template:w,inject:["froshToolsService","repositoryFactory","themeService"],mixins:[I.getByName("notification")],data(){return{cacheInfo:null,isLoading:!0,numberFormater:null}},created(){let s=Shopware.Application.getContainer("factory").locale.getLastKnownLocale();this.numberFormater=new Intl.NumberFormat(s,{minimumFractionDigits:2,maximumFractionDigits:2}),this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"size",label:"frosh-tools.used",rawData:!0,align:"right"},{property:"freeSpace",label:"frosh-tools.free",rawData:!0,align:"right"}]},cacheFolders(){return this.cacheInfo===null?[]:this.cacheInfo},salesChannelRepository(){return this.repositoryFactory.create("sales_channel")}},methods:{async createdComponent(){this.isLoading=!0,this.cacheInfo=await this.froshToolsService.getCacheInfo(),this.isLoading=!1},formatSize(s){let e=s/1048576;return this.numberFormater.format(e)+" MiB"},async clearCache(s){this.isLoading=!0,await this.froshToolsService.clearCache(s.name),await this.createdComponent()},async compileTheme(){let s=new P;s.addAssociation("themes"),this.isLoading=!0;let e=await this.salesChannelRepository.search(s,Shopware.Context.api);for(let t of e){let a=t.extensions.themes.first();a&&(await this.themeService.assignTheme(a.id,t.id),this.createNotificationSuccess({message:`${t.translated.name}: ${this.$tc("frosh-tools.themeCompiled")}`}))}this.isLoading=!1}}});var b=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-queue__manager-card"
            :title="$tc('frosh-tools.tabs.queue.title')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-queue"
    >
        <template #toolbar>
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="regular-undo"></sw-icon></sw-button>
            <sw-button variant="danger" @click="showResetModal = true">{{ $tc('frosh-tools.resetQueue') }}</sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="queueEntries"
            :columns="columns"
            :showActions="false"
        >
        </sw-data-grid>
    </sw-card>

    <sw-modal v-if="showResetModal" :title="$tc('frosh-tools.tabs.queue.reset.modal.title')" variant="small" @modal-close="showResetModal = false">
        {{ $tc('frosh-tools.tabs.queue.reset.modal.description') }}

        <template #modal-footer>
            <sw-button @click="showResetModal = false">{{ $tc('global.default.cancel') }}</sw-button>
            <sw-button variant="danger" @click="resetQueue">{{ $tc('frosh-tools.tabs.queue.reset.modal.reset') }}</sw-button>
        </template>
    </sw-modal>
</sw-card-view>
`;var{Component:M,Mixin:N}=Shopware;M.register("frosh-tools-tab-queue",{template:b,inject:["repositoryFactory","froshToolsService"],mixins:[N.getByName("notification")],data(){return{queueEntries:[],showResetModal:!1,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"Name",rawData:!0},{property:"size",label:"Size",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.queueEntries=await this.froshToolsService.getQueue();for(let s of this.queueEntries){let e=s.name.split("\\");s.name=e[e.length-1]}this.isLoading=!1},async resetQueue(){this.isLoading=!0,await this.froshToolsService.resetQueue(),this.showResetModal=!1,await this.createdComponent(),this.createNotificationSuccess({message:this.$tc("frosh-tools.tabs.queue.reset.success")}),this.isLoading=!1}}});var v=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-scheduled__tasks-card"
            :title="$tc('frosh-tools.tabs.scheduledTaskOverview.title')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-scheduled"
    >

        <template #toolbar>
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="regular-undo"></sw-icon></sw-button>
            <sw-button variant="primary" @click="registerScheduledTasks">{{ $tc('frosh-tools.scheduledTasksRegisterStarted') }}</sw-button>
        </template>

        <sw-entity-listing
            :showSelection="false"
            :fullPage="false"
            :allowInlineEdit="true"
            :allowEdit="false"
            :allowDelete="false"
            :showActions="true"
            :repository="scheduledRepository"
            :items="items"
            :columns="columns">

            <template #column-lastExecutionTime="{ item }">
                {{ item.lastExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
            </template>
            <template #column-nextExecutionTime="{ item, column, compact, isInlineEdit }">
                <sw-data-grid-inline-edit
                    v-if="isInlineEdit"
                    :column="column"
                    :compact="compact"
                    :value="item[column.property]"
                    @input="item[column.property] = $event">
                </sw-data-grid-inline-edit>

                <span v-else>
                     {{ item.nextExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
                </span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="primary" @click="runTask(item)">
                    {{ $tc('frosh-tools.runManually') }}
                </sw-context-menu-item>
            </template>
        </sw-entity-listing>
    </sw-card>
</sw-card-view>
`;var{Component:H,Mixin:q}=Shopware,{Criteria:S}=Shopware.Data;H.register("frosh-tools-tab-scheduled",{template:v,inject:["repositoryFactory","froshToolsService"],mixins:[q.getByName("notification")],data(){return{items:null,showResetModal:!1,isLoading:!0,page:1,limit:25}},created(){this.createdComponent()},computed:{scheduledRepository(){return this.repositoryFactory.create("scheduled_task")},columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"runInterval",label:"frosh-tools.interval",rawData:!0,inlineEdit:"number"},{property:"lastExecutionTime",label:"frosh-tools.lastExecutionTime",rawData:!0},{property:"nextExecutionTime",label:"frosh-tools.nextExecutionTime",rawData:!0,inlineEdit:"datetime"},{property:"status",label:"frosh-tools.status",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){let s=new S(this.page,this.limit);s.addSorting(S.sort("nextExecutionTime","ASC")),this.items=await this.scheduledRepository.search(s,Shopware.Context.api),this.isLoading=!1},async runTask(s){this.isLoading=!0;try{this.createNotificationInfo({message:this.$tc("frosh-tools.scheduledTaskStarted",0,{name:s.name})}),await this.froshToolsService.runScheduledTask(s.id),this.createNotificationSuccess({message:this.$tc("frosh-tools.scheduledTaskSucceed",0,{name:s.name})})}catch{this.createNotificationError({message:this.$tc("frosh-tools.scheduledTaskFailed",0,{name:s.name})})}this.createdComponent()},async registerScheduledTasks(){this.isLoading=!0;try{this.createNotificationInfo({message:this.$tc("frosh-tools.scheduledTasksRegisterStarted")}),await this.froshToolsService.scheduledTasksRegister(),this.createNotificationSuccess({message:this.$tc("frosh-tools.scheduledTasksRegisterSucceed")})}catch{this.createNotificationError({message:this.$tc("frosh-tools.scheduledTasksRegisterFailed")})}this.createdComponent()}}});var y=`<sw-card-view>
    <sw-card
            :title="$tc('frosh-tools.tabs.elasticsearch.title')"
            :large="true"
            :isLoading="isLoading"
            positionIdentifier="frosh-tools-tab-elasticsearch"
    >
        <sw-alert variant="error" v-if="!isLoading && !isActive">Elasticsearch is not enabled</sw-alert>

        <div v-if="!isLoading && isActive">
            <div><strong>Elasticsearch version: </strong> {{ statusInfo.info.version.number }}</div>
            <div><strong>Nodes: </strong> {{ statusInfo.health.number_of_nodes }}</div>
            <div><strong>Cluster status: </strong> {{ statusInfo.health.status }}</div>
        </div>
    </sw-card>

    <sw-card
            title="Indices"
            v-if="!isLoading && isActive"
            :large="true"
            positionIdentifier="frosh-tools-tab-elasticsearch-indices"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="regular-undo"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            v-if="indices"
            :showSelection="false"
            :dataSource="indices"
            :columns="columns">

            <template #column-name="{ item }">
                <sw-label variant="primary" appearance="pill" v-if="item.aliases.length">
                    {{ $tc('frosh-tools.active') }}
                </sw-label>

                {{ item.name }}<br>
            </template>

            <template #column-indexSize="{ item }">
                {{ formatSize(item.indexSize) }}<br>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="deleteIndex(item.name)">
                    {{ $tc('frosh-tools.delete') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card
            title="Actions"
            v-if="!isLoading && isActive"
            :large="true"
            positionIdentifier="frosh-tools-tab-elasticsearch-health"
    >
        <sw-button @click="reindex" variant="primary">Reindex</sw-button>
        <sw-button @click="switchAlias">Trigger alias switching</sw-button>
        <sw-button @click="flushAll">Flush all indices</sw-button>

        <sw-button @click="cleanup">Cleanup unused Indices</sw-button>
        <sw-button @click="resetElasticsearch" variant="danger">Delete all indices</sw-button>
    </sw-card>

    <sw-card
            title="Elasticsearch Console"
            v-if="!isLoading && isActive"
            :large="true"
            positionIdentifier="frosh-tools-tab-elasticsearch-console"
    >
        <sw-code-editor
            completionMode="text"
            mode="twig"
            :softWraps="true"
            :setFocus="false"
            :disabled="false"
            :sanitizeInput="false"
            v-model="consoleInput"
        ></sw-code-editor>

        <sw-button @click="onConsoleEnter">Send</sw-button>

        <div><strong>Output:</strong></div>

        <pre>{{ consoleOutput }}</pre>
    </sw-card>
</sw-card-view>
`;var{Mixin:O,Component:j}=Shopware;j.register("frosh-tools-tab-elasticsearch",{template:y,inject:["froshElasticSearch"],mixins:[O.getByName("notification")],data(){return{isLoading:!0,isActive:!0,statusInfo:{},indices:[],consoleInput:"GET /_cat/indices",consoleOutput:{}}},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"indexSize",label:"frosh-tools.size",rawData:!0,primary:!0},{property:"docs",label:"frosh-tools.docs",rawData:!0,primary:!0}]}},created(){this.createdComponent()},methods:{async createdComponent(){this.isLoading=!0;try{this.statusInfo=await this.froshElasticSearch.status()}catch{this.isActive=!1,this.isLoading=!1;return}finally{this.isLoading=!1}this.indices=await this.froshElasticSearch.indices()},formatSize(s){let a=s;if(Math.abs(s)<1024)return s+" B";let n=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],r=-1,d=10**1;do a/=1024,++r;while(Math.round(Math.abs(a)*d)/d>=1024&&r<n.length-1);return a.toFixed(1)+" "+n[r]},async deleteIndex(s){await this.froshElasticSearch.deleteIndex(s),await this.createdComponent()},async onConsoleEnter(){let s=this.consoleInput.split(`
`),e=s.shift(),t=s.join(`
`).trim(),[a,n]=e.split(" ");try{this.consoleOutput=await this.froshElasticSearch.console(a,n,t)}catch(r){this.consoleOutput=r.response.data}},async reindex(){await this.froshElasticSearch.reindex(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async switchAlias(){await this.froshElasticSearch.switchAlias(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async flushAll(){await this.froshElasticSearch.flushAll(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async resetElasticsearch(){await this.froshElasticSearch.reset(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async cleanup(){await this.froshElasticSearch.cleanup(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()}}});var x=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-logs__logs-card"
            :title="$tc('frosh-tools.tabs.logs.title')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-logs"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="regular-undo"></sw-icon></sw-button>
            <sw-single-select
                :options="logFiles"
                :isLoading="isLoading"
                :placeholder="$tc('frosh-tools.tabs.logs.logFileSelect.placeholder')"
                labelProperty="name"
                valueProperty="name"
                v-model="selectedLogFile"
                @change="onFileSelected"
            ></sw-single-select>
        </template>

        <sw-data-grid
            :showSelection="false"
            :showActions="false"
            :dataSource="logEntries"
            :columns="columns">
            <template slot="column-date" slot-scope="{ item }">
                {{ item.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
            </template>
            <template slot="column-message" slot-scope="{ item }">
                <a @click="showInfoModal(item)">{{ item.message }}</a>
            </template>
        </sw-data-grid>

        <sw-pagination
            :total="totalLogEntries"
            :limit="limit"
            :page="page"
            @page-change="onPageChange"
        ></sw-pagination>
    </sw-card>

    <sw-modal v-if="displayedLog"
              variant="large">

        <template slot="modal-header">
            <div class="sw-modal__titles">
                <h4 class="sw-modal__title">
                    {{ displayedLog.channel }} - {{ displayedLog.level }}
                </h4>

                <h5 class="sw-modal__subtitle">
                    {{ displayedLog.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
                </h5>
            </div>

            <button
                class="sw-modal__close"
                :title="$tc('global.sw-modal.labelClose')"
                :aria-label="$tc('global.sw-modal.labelClose')"
                @click="closeInfoModal"
            >
                <sw-icon
                    name="regular-times-s"
                    small
                />
            </button>
        </template>

        <div v-html="displayedLog.message"></div>
    </sw-modal>
</sw-card-view>
`;var{Component:G,Mixin:W}=Shopware;G.register("frosh-tools-tab-logs",{template:x,inject:["froshToolsService"],mixins:[W.getByName("notification")],data(){return{logFiles:[],selectedLogFile:null,logEntries:[],totalLogEntries:0,limit:25,page:1,isLoading:!0,displayedLog:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"date",label:"frosh-tools.date",rawData:!0},{property:"channel",label:"frosh-tools.channel",rawData:!0},{property:"level",label:"frosh-tools.level",rawData:!0},{property:"message",label:"frosh-tools.message",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent(),await this.onFileSelected()},async createdComponent(){this.logFiles=await this.froshToolsService.getLogFiles(),this.isLoading=!1},async onFileSelected(){if(!this.selectedLogFile)return;let s=await this.froshToolsService.getLogFile(this.selectedLogFile,(this.page-1)*this.limit,this.limit);this.logEntries=s.data,this.totalLogEntries=parseInt(s.headers["file-size"],10)},async onPageChange(s){this.page=s.page,this.limit=s.limit,await this.onFileSelected()},showInfoModal(s){this.displayedLog=s},closeInfoModal(){this.displayedLog=null}}});var C=`<sw-card-view>
    <sw-card
            class="frosh-tools-tab-state-machines__state-machines-card"
            :title="$tc('frosh-tools.tabs.state-machines.title')"
            :isLoading="isLoading"
            :large="true"
            positionIdentifier="frosh-tools-tab-state-machines"
    >

        <div class="frosh-tools-tab-state-machines__state-machines-card-image-wrapper">
            <img id="state_machine"
                 class="frosh-tools-tab-state-machines__state-machines-card-image"
                 type="image/svg+xml"
                 src="/bundles/administration/static/img/empty-states/media-empty-state.svg"
                 alt="State Machine"
                 width="100%"
                 height="auto"
                 style="text-align:center; display:inline-block; opacity:0;"
            />
        </div>

        <template #toolbar>
            <sw-select-field
                    size="small"
                    :aside="true"
                    @change="onStateMachineChange"
                    :label=" $tc('frosh-tools.tabs.state-machines.label')"
                    :helpText="$tc('frosh-tools.tabs.state-machines.helpText')"
            >
                <option selected="selected" value="">{{ $tc('frosh-tools.chooseStateMachine') }}</option>
                <option value="order.state">{{ $tc('frosh-tools.order') }}</option>
                <option value="order_transaction.state">{{ $tc('frosh-tools.transaction') }}</option>
                <option value="order_delivery.state">{{ $tc('frosh-tools.delivery') }}</option>
            </sw-select-field>
        </template>

    </sw-card>
</sw-card-view>
`;var{Component:V,Mixin:Y}=Shopware;V.register("frosh-tools-tab-state-machines",{template:C,inject:["froshToolsService"],mixins:[Y.getByName("notification")],data(){return{image:null,isLoading:!0}},created(){this.createdComponent()},methods:{createdComponent(){this.isLoading=!1},async onStateMachineChange(s){let e=await this.froshToolsService.stateMachines(s),t=document.getElementById("state_machine");"svg"in e?(this.image=e.svg,t.src=this.image,t.style.opacity="1",t.style.width="100%",t.style.height="auto"):t.style.opacity="0"}}});var $=`<sw-page class="frosh-tools">
    <template slot="content">
        <sw-container>
            <sw-tabs :small="false" positionIdentifier="frosh-tools-tabs">
                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">
                    {{ $tc('frosh-tools.tabs.index.title') }}
                </sw-tabs-item>

{#                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">#}
{#                    {{ $tc('frosh-tools.tabs.systemInfo.title') }}#}
{#                </sw-tabs-item>#}

                <sw-tabs-item :route="{ name: 'frosh.tools.index.cache' }">
                    {{ $tc('frosh-tools.tabs.cache.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.scheduled' }">
                    {{ $tc('frosh-tools.tabs.scheduledTaskOverview.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.queue' }">
                    {{ $tc('frosh-tools.tabs.queue.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.logs' }">
                    {{ $tc('frosh-tools.tabs.logs.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.elasticsearch' }" v-if="elasticsearchAvailable">
                    {{ $tc('frosh-tools.tabs.elasticsearch.title') }}
                </sw-tabs-item>
                
                <sw-tabs-item :route="{ name: 'frosh.tools.index.statemachines' }">
                    {{ $tc('frosh-tools.tabs.state-machines.title') }}
                </sw-tabs-item>

            </sw-tabs>
        </sw-container>

        <router-view></router-view>
    </template>
</sw-page>
`;var{Component:J}=Shopware;J.register("frosh-tools-index",{template:$,computed:{elasticsearchAvailable(){return Shopware.State.get("context").app.config.settings?.elasticsearchEnabled||!1}}});Shopware.Service("privileges").addPrivilegeMappingEntry({category:"additional_permissions",parent:null,key:"frosh_tools",roles:{frosh_tools:{privileges:["frosh_tools:read"],dependencies:[]}}});Shopware.Module.register("frosh-tools",{type:"plugin",name:"frosh-tools.title",title:"frosh-tools.title",description:"",color:"#303A4F",icon:"regular-dashboard",routes:{index:{component:"frosh-tools-index",path:"index",children:{index:{component:"frosh-tools-tab-index",path:"index",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},cache:{component:"frosh-tools-tab-cache",path:"cache",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},queue:{component:"frosh-tools-tab-queue",path:"queue",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},scheduled:{component:"frosh-tools-tab-scheduled",path:"scheduled",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},elasticsearch:{component:"frosh-tools-tab-elasticsearch",path:"elasticsearch",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},logs:{component:"frosh-tools-tab-logs",path:"logs",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},statemachines:{component:"frosh-tools-tab-state-machines",path:"state-machines",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}}}}},settingsItem:[{group:"plugins",to:"frosh.tools.index.cache",icon:"default-action-settings",name:"frosh-tools.title"}]});})();
