var template = document.getElementsByClassName("novaix_product_detail")[0];
Vue.prototype.lang = Object.assign(window.lang || {}, window.module_lang || {});

new Vue({
  components: {
    safeConfirm: safeConfirm,
  },
  data: function () {
    return {
      id: "",
      host: {},
      hostData: {},
      images: [],
      commonData: {},
      activeName: "info",
      initLoading: true,
      postPowerStatus: "",
      statusTimer: null,
      pollCount: 0,
      client_operate_password: "",
      pendingSafeAction: null,
      actionLoading: false,

      // 状态映射
      statusMap: {
        Unpaid:    { text: window.lang.status_unpaid    || "Unpaid",    color: "#E6A23C", bg: "#FDF6EC" },
        Pending:   { text: window.lang.status_pending   || "Pending",   color: "#409EFF", bg: "#ECF5FF" },
        Active:    { text: window.lang.status_active    || "Active",    color: "#67C23A", bg: "#F0F9EB" },
        Suspended: { text: window.lang.status_suspended || "Suspended", color: "#E6A23C", bg: "#FDF6EC" },
        Deleted:   { text: window.lang.status_deleted   || "Deleted",   color: "#909399", bg: "#F4F4F5" },
        Failed:    { text: window.lang.status_pending   || "Pending",   color: "#409EFF", bg: "#ECF5FF" },
      },

      // 电源操作
      powerAction: "on",
      powerOptions: [
        { func: "on",     name: (window.module_lang || {}).novaix_btn_on     || "Power On" },
        { func: "off",    name: (window.module_lang || {}).novaix_btn_off    || "Power Off" },
        { func: "reboot", name: (window.module_lang || {}).novaix_btn_reboot || "Reboot" },
      ],
      powerDialogVisible: false,

      // 重装
      reinstallDialogVisible: false,
      reinstallData: { osGroup: "", imageId: "" },
      osGroups: [],
      osVersions: [],

      // 重置密码
      rePassDialogVisible: false,
      rePassData: { password: "" },
    };
  },
  computed: {
    powerActionName: function () {
      for (var i = 0; i < this.powerOptions.length; i++) {
        if (this.powerOptions[i].func === this.powerAction) return this.powerOptions[i].name;
      }
      return "";
    },
    powerStatusText: function () {
      var lang = this.lang;
      var map = {
        running: lang.novaix_status_running,
        stopped: lang.novaix_status_stopped,
        frozen: lang.novaix_status_frozen,
        creating: lang.novaix_status_creating,
        error: lang.novaix_status_error,
        operating: lang.novaix_status_operating,
      };
      return map[this.postPowerStatus] || this.postPowerStatus || "--";
    },
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
    var search = location.href.split("?")[1] || "";
    var pairs = search.split("&");
    for (var i = 0; i < pairs.length; i++) {
      var kv = pairs[i].split("=");
      if (kv[0] === "id") this.id = kv[1];
    }
    this.getCommonData();
    this.getDetail();
  },
  mounted: function () {
    template.style.display = "block";
  },
  beforeDestroy: function () {
    this.stopPolling();
  },
  methods: {
    getCommonData: function () {
      try {
        this.commonData = JSON.parse(localStorage.getItem("common_set_before")) || {};
      } catch (e) {
        this.commonData = {};
      }
    },

    getDetail: function () {
      var self = this;
      novaix_getHostDetail(self.id).then(function (res) {
        self.initLoading = false;
        if (res.data.status === 200) {
          var data = res.data.data;
          self.host = data.host || {};
          self.hostData = data.host || {};
          self.images = data.host.images || [];
          self.postPowerStatus = self.host.raw_status || "";
          self.initOsGroups();
          if (self.isTransitStatus(self.postPowerStatus)) {
            self.startPolling();
          }
        }
      }).catch(function () {
        self.initLoading = false;
      });
    },

    // 状态轮询
    isTransitStatus: function (s) {
      return s === "creating" || s === "operating";
    },
    startPolling: function () {
      var self = this;
      self.stopPolling();
      self.pollCount = 0;
      self.statusTimer = setInterval(function () {
        self.pollCount++;
        if (self.pollCount > 60) {
          self.stopPolling();
          return;
        }
        novaix_getStatus(self.id).then(function (res) {
          if (res.data.status === 200) {
            var s = res.data.data.status || "";
            self.postPowerStatus = s;
            if (!self.isTransitStatus(s)) {
              self.stopPolling();
              self.getDetail();
            }
          }
        });
      }, 3000);
    },
    stopPolling: function () {
      if (this.statusTimer) {
        clearInterval(this.statusTimer);
        this.statusTimer = null;
      }
    },

    // 安全验证
    requireSafe: function (action) {
      this.pendingSafeAction = action;
      if (this.$refs.safeRef) {
        this.$refs.safeRef.openDialog();
      } else {
        action();
      }
    },
    handleSafeConfirm: function () {
      if (this.pendingSafeAction) {
        this.pendingSafeAction();
        this.pendingSafeAction = null;
      }
    },

    // 电源操作
    showPowerDialog: function () {
      this.powerDialogVisible = true;
    },
    doPower: function () {
      var self = this;
      self.requireSafe(function () {
        self.actionLoading = true;
        novaix_provision(self.id, self.powerAction, {
          client_operate_password: self.client_operate_password,
        }).then(function (res) {
          self.actionLoading = false;
          self.powerDialogVisible = false;
          if (res.data.status === 200) {
            self.$message.success(self.lang.novaix_success);
            self.postPowerStatus = "operating";
            self.startPolling();
          } else {
            self.$message.error(res.data.msg || self.lang.novaix_fail);
          }
        }).catch(function () {
          self.actionLoading = false;
        });
      });
    },

    // VNC
    doVnc: function () {
      var self = this;
      self.requireSafe(function () {
        novaix_getVnc(self.id, {
          client_operate_password: self.client_operate_password,
        }).then(function (res) {
          if (res.data.status === 200 && res.data.data.console_url) {
            window.open(res.data.data.console_url, "_blank");
          } else {
            self.$message.error(res.data.msg || self.lang.novaix_fail);
          }
        });
      });
    },

    // 重装系统
    initOsGroups: function () {
      var groups = {};
      for (var i = 0; i < this.images.length; i++) {
        var img = this.images[i];
        var os = img.os || "Other";
        if (!groups[os]) groups[os] = { os: os, items: [] };
        groups[os].items.push(img);
      }
      this.osGroups = Object.values(groups);
      if (this.osGroups.length > 0) {
        this.reinstallData.osGroup = this.osGroups[0].os;
        this.osGroupChange(this.osGroups[0].os);
      }
    },
    osGroupChange: function (os) {
      for (var i = 0; i < this.osGroups.length; i++) {
        if (this.osGroups[i].os === os) {
          this.osVersions = this.osGroups[i].items;
          if (this.osVersions.length > 0) {
            this.reinstallData.imageId = this.osVersions[0].id;
          } else {
            this.reinstallData.imageId = "";
          }
          return;
        }
      }
    },
    showReinstall: function () {
      this.reinstallDialogVisible = true;
    },
    doReinstall: function () {
      var self = this;
      if (!self.reinstallData.imageId) {
        self.$message.warning(self.lang.novaix_reinstall_select_ver);
        return;
      }
      self.requireSafe(function () {
        self.actionLoading = true;
        novaix_provision(self.id, "reinstall", {
          image_id: self.reinstallData.imageId,
          client_operate_password: self.client_operate_password,
        }).then(function (res) {
          self.actionLoading = false;
          self.reinstallDialogVisible = false;
          if (res.data.status === 200) {
            self.$message.success(self.lang.novaix_success);
            self.postPowerStatus = "operating";
            self.startPolling();
          } else {
            self.$message.error(res.data.msg || self.lang.novaix_fail);
          }
        }).catch(function () {
          self.actionLoading = false;
        });
      });
    },

    // 重置密码
    showRePass: function () {
      this.rePassData.password = "";
      this.rePassDialogVisible = true;
    },
    autoPass: function () {
      var chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
      var pass = "";
      for (var i = 0; i < 16; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      this.rePassData.password = pass;
    },
    doRePass: function () {
      var self = this;
      if (!self.rePassData.password) {
        self.$message.warning(self.lang.novaix_reset_pass_input);
        return;
      }
      self.requireSafe(function () {
        self.actionLoading = true;
        novaix_provision(self.id, "crack_pass", {
          password: self.rePassData.password,
          client_operate_password: self.client_operate_password,
        }).then(function (res) {
          self.actionLoading = false;
          self.rePassDialogVisible = false;
          if (res.data.status === 200) {
            self.$message.success(self.lang.novaix_success);
          } else {
            self.$message.error(res.data.msg || self.lang.novaix_fail);
          }
        }).catch(function () {
          self.actionLoading = false;
        });
      });
    },

    // 工具方法
    formatMemory: function (mb) {
      if (!mb) return "--";
      return mb >= 1024 ? (mb / 1024).toFixed(1) + " GB" : mb + " MB";
    },
    copyText: function (text) {
      var self = this;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
          self.$message.success(self.lang.novaix_copy_success);
        });
      } else {
        var ta = document.createElement("textarea");
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
        self.$message.success(self.lang.novaix_copy_success);
      }
    },
    back: function () {
      history.back();
    },
  },
}).$mount(template);
