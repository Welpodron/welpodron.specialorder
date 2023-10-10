"use strict";
((window) => {
    if (window.welpodron && window.welpodron.templater) {
        if (window.welpodron.specialorder) {
            return;
        }
        const MODULE_BASE = "specialorder";
        const EVENT_ADD_BEFORE = `welpodron.${MODULE_BASE}:add:before`;
        const EVENT_ADD_AFTER = `welpodron.${MODULE_BASE}:add:after`;
        const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
        const ATTRIBUTE_RESPONSE = `${ATTRIBUTE_BASE}-response`;
        const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
        const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
        const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
        const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;
        class SpecialOrder {
            sessid = "";
            supportedActions = ["add"];
            constructor({ sessid, element, config = {} }) {
                if (SpecialOrder.instance) {
                    if (sessid) {
                        SpecialOrder.instance.sessid = sessid;
                    }
                    return SpecialOrder.instance;
                }
                this.setSessid(sessid);
                document.removeEventListener("click", this.handleDocumentClick);
                document.addEventListener("click", this.handleDocumentClick);
                SpecialOrder.instance = this;
            }
            handleDocumentClick = (event) => {
                let { target } = event;
                if (!target) {
                    return;
                }
                target = target.closest(`[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}]`);
                if (!target) {
                    return;
                }
                const action = target.getAttribute(ATTRIBUTE_ACTION);
                const actionArgs = target.getAttribute(ATTRIBUTE_ACTION_ARGS);
                const actionFlush = target.getAttribute(ATTRIBUTE_ACTION_FLUSH);
                if (!actionFlush) {
                    event.preventDefault();
                }
                if (!action || !this.supportedActions.includes(action)) {
                    return;
                }
                const actionFunc = this[action];
                if (actionFunc instanceof Function) {
                    return actionFunc({
                        args: actionArgs,
                        event,
                    });
                }
            };
            setSessid = (sessid) => {
                this.sessid = sessid;
            };
            add = async ({ args, event, }) => {
                if (!args) {
                    return;
                }
                const controls = document.querySelectorAll(`[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}="${args}"]`);
                controls.forEach((control) => {
                    control.setAttribute("disabled", "");
                });
                let data = args instanceof FormData ? args : new FormData();
                // composite and deep cache fix
                if (window.BX && window.BX.bitrix_sessid) {
                    this.setSessid(window.BX.bitrix_sessid());
                }
                data.set("sessid", this.sessid);
                if (!(args instanceof FormData)) {
                    let json = "";
                    try {
                        JSON.parse(args);
                        json = args;
                    }
                    catch (_) {
                        json = JSON.stringify(args);
                    }
                    data.set("args", json);
                }
                let dispatchedEvent = new CustomEvent(EVENT_ADD_BEFORE, {
                    bubbles: true,
                    cancelable: false,
                });
                document.dispatchEvent(dispatchedEvent);
                let responseData = {};
                let bitrixResponse = null;
                try {
                    const response = await fetch("/bitrix/services/main/ajax.php?action=welpodron%3Aspecialorder.Receiver.add", {
                        method: "POST",
                        body: data,
                    });
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    bitrixResponse = await response.json();
                    if (!bitrixResponse) {
                        throw new Error("Ожидался другой формат ответа от сервера");
                    }
                    if (bitrixResponse.status === "error") {
                        console.error(bitrixResponse);
                        const error = bitrixResponse.errors[0];
                        if (!event || !event?.target) {
                            return bitrixResponse;
                        }
                        const target = event.target.closest(`[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`);
                        if (!target || !target.parentElement) {
                            return bitrixResponse;
                        }
                        let div = target.parentElement.querySelector(`[${ATTRIBUTE_RESPONSE}]`);
                        if (!div) {
                            div = document.createElement("div");
                            div.setAttribute(ATTRIBUTE_RESPONSE, "");
                            target.parentElement.appendChild(div);
                        }
                        window.welpodron.templater.renderHTML({
                            string: error.message,
                            container: div,
                            config: {
                                replace: true,
                            },
                        });
                    }
                    else {
                        responseData = bitrixResponse.data;
                        if (responseData.HTML != null) {
                            if (event && event?.target) {
                                const target = event.target.closest(`[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`);
                                if (target && target.parentElement) {
                                    let div = target.parentElement.querySelector(`[${ATTRIBUTE_RESPONSE}]`);
                                    if (!div) {
                                        div = document.createElement("div");
                                        div.setAttribute(ATTRIBUTE_RESPONSE, "");
                                        target.parentElement.appendChild(div);
                                    }
                                    window.welpodron.templater.renderHTML({
                                        string: responseData.HTML,
                                        container: div,
                                        config: {
                                            replace: true,
                                        },
                                    });
                                }
                            }
                        }
                        if (window.welpodron.specialbasket) {
                            new window.welpodron.specialbasket({
                                sessid: this.sessid,
                                items: [],
                                config: {
                                    forceItems: true,
                                },
                            });
                        }
                    }
                }
                catch (error) {
                    console.error(error);
                }
                finally {
                    dispatchedEvent = new CustomEvent(EVENT_ADD_AFTER, {
                        bubbles: true,
                        cancelable: false,
                        detail: responseData,
                    });
                    document.dispatchEvent(dispatchedEvent);
                    controls.forEach((control) => {
                        control.removeAttribute("disabled");
                    });
                }
                return bitrixResponse;
            };
        }
        window.welpodron.specialorder = SpecialOrder;
    }
})(window);
//# sourceMappingURL=script.js.map