"use strict";
var __extends = (this && this.__extends) || (function () {
    var extendStatics = Object.setPrototypeOf ||
        ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
        function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
var WebhookExecutor_class_1 = require("./WebhookExecutor.class");
var db_1 = require("../db");
var RepositoryWebhookExecutor = (function (_super) {
    __extends(RepositoryWebhookExecutor, _super);
    function RepositoryWebhookExecutor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    RepositoryWebhookExecutor.prototype.getTasks = function () {
        var _this = this;
        return [
            function (onComplete, onError) {
                if (_this.payload.action === "publicized" || _this.payload.action === "privatized") {
                    db_1.db.update("repos", { private: _this.payload.action === "privatized" }, "repoId = ?", [_this.payload.repository.id], onError, onComplete);
                }
                else {
                    onComplete();
                }
            },
        ];
    };
    return RepositoryWebhookExecutor;
}(WebhookExecutor_class_1.WebhookExecutor));
exports.RepositoryWebhookExecutor = RepositoryWebhookExecutor;