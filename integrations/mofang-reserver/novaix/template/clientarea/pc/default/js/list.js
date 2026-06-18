var template = document.getElementsByClassName("novaix_product_list")[0];
Vue.prototype.lang = Object.assign(window.lang || {}, window.module_lang || {});

new Vue({
  components: {
    pagination: pagination,
  },
  data: function () {
    return {
      params: {
        page: 1,
        limit: 20,
        pageSizes: [20, 50, 100],
        total: 0,
        orderby: "id",
        sort: "desc",
        keywords: "",
        status: "",
        m: "",
      },
      commonList: [],
      commonData: {},
      loading: false,
      statusMap: {
        Unpaid:    { text: window.lang.status_unpaid    || "Unpaid",    color: "#E6A23C", bg: "#FDF6EC" },
        Pending:   { text: window.lang.status_pending   || "Pending",   color: "#409EFF", bg: "#ECF5FF" },
        Active:    { text: window.lang.status_active    || "Active",    color: "#67C23A", bg: "#F0F9EB" },
        Suspended: { text: window.lang.status_suspended || "Suspended", color: "#E6A23C", bg: "#FDF6EC" },
        Deleted:   { text: window.lang.status_deleted   || "Deleted",   color: "#909399", bg: "#F4F4F5" },
        Failed:    { text: window.lang.status_pending   || "Pending",   color: "#409EFF", bg: "#ECF5FF" },
      },
      statusSelect: [
        { status: "Active",    label: window.lang.status_active    || "Active" },
        { status: "Pending",   label: window.lang.status_pending   || "Pending" },
        { status: "Suspended", label: window.lang.status_suspended || "Suspended" },
        { status: "Deleted",   label: window.lang.status_deleted   || "Deleted" },
      ],
    };
  },
  filters: {
    formateTime: function (time) {
      if (!time) return "--";
      var d = new Date(time * 1000);
      var pad = function (n) { return n < 10 ? "0" + n : n; };
      return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) + " " + pad(d.getHours()) + ":" + pad(d.getMinutes());
    },
  },
  created: function () {
    this.analysisUrl();
    this.getCommonData();
    this.getList();
  },
  methods: {
    analysisUrl: function () {
      var search = location.search;
      if (search) {
        var pairs = search.replace("?", "").split("&");
        for (var i = 0; i < pairs.length; i++) {
          var kv = pairs[i].split("=");
          if (kv[0] === "m") this.params.m = kv[1];
        }
      }
    },
    getCommonData: function () {
      try {
        this.commonData = JSON.parse(localStorage.getItem("common_set_before")) || {};
      } catch (e) {
        this.commonData = {};
      }
    },
    getList: function () {
      var self = this;
      self.loading = true;
      novaix_getHostList(self.params).then(function (res) {
        self.loading = false;
        if (res.data.status === 200) {
          self.commonList = res.data.data.list || [];
          self.params.total = res.data.data.count || 0;
        }
      }).catch(function () {
        self.loading = false;
      });
    },
    toDetail: function (row) {
      location.href = "productdetail.htm?id=" + row.id;
    },
    inputChange: function () {
      this.params.page = 1;
      this.getList();
    },
    clearKey: function () {
      this.params.keywords = "";
      this.inputChange();
    },
    sizeChange: function (e) {
      this.params.limit = e;
      this.params.page = 1;
      this.getList();
    },
    currentChange: function (e) {
      this.params.page = e;
      this.getList();
    },
  },
}).$mount(template);
