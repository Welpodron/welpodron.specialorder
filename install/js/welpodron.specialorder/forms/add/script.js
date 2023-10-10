"use strict";
((window) => {
    if (window.welpodron && window.welpodron.templater) {
        if (!window.welpodron.specialorder) {
            return;
        }
        if (!window.welpodron.forms) {
            window.welpodron.forms = {};
        }
        if (!window.welpodron.forms.specialorder) {
            window.welpodron.forms.specialorder = {};
        }
        if (window.welpodron.forms.specialorder.add) {
            return;
        }
        const FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
        class AddForm {
            element;
            isDisabled = false;
            constructor({ element, config = {} }) {
                this.element = element;
                this.element.removeEventListener("input", this.handleFormInput);
                this.element.addEventListener("input", this.handleFormInput);
                this.element.removeEventListener("submit", this.handleFormSubmit);
                this.element.addEventListener("submit", this.handleFormSubmit);
                // v4
                this.disable();
                if (this.element.checkValidity()) {
                    this.enable();
                }
            }
            handleFormSubmit = async (event) => {
                event.preventDefault();
                if (this.isDisabled) {
                    return;
                }
                this.disable();
                const data = new FormData(this.element);
                // composite and deep cache fix
                if (window.BX && window.BX.bitrix_sessid) {
                    data.set("sessid", window.BX.bitrix_sessid());
                }
                debugger;
                const order = new window.welpodron.specialorder({
                    sessid: null,
                });
                try {
                    const result = await order.add({
                        args: data,
                        event,
                    });
                    if (!result) {
                        this.enable();
                        return;
                    }
                    if (result.status === "error") {
                        const error = result.errors[0];
                        if (error.code === FIELD_VALIDATION_ERROR_CODE) {
                            const field = this.element.elements[error.customData];
                            if (field) {
                                field.setCustomValidity(error.message);
                                field.reportValidity();
                                field.addEventListener("input", () => {
                                    field.setCustomValidity("");
                                    field.reportValidity();
                                    field.checkValidity();
                                }, {
                                    once: true,
                                });
                            }
                        }
                        this.enable();
                        return;
                    }
                    if (result.status === "success") {
                        // this.element.reset();
                        if (this.element.checkValidity()) {
                            this.enable();
                        }
                        else {
                            this.disable();
                        }
                    }
                }
                catch (error) {
                    console.error(error);
                }
                finally {
                    if (this.element.checkValidity()) {
                        this.enable();
                    }
                    else {
                        this.disable();
                    }
                }
            };
            // v4
            handleFormInput = (event) => {
                if (this.element.checkValidity()) {
                    return this.enable();
                }
                this.disable();
            };
            // v4
            disable = () => {
                this.isDisabled = true;
                [...this.element.elements]
                    .filter((element) => {
                    return ((element instanceof HTMLButtonElement ||
                        element instanceof HTMLInputElement) &&
                        element.type === "submit");
                })
                    .forEach((element) => {
                    element.setAttribute("disabled", "");
                });
            };
            // v4
            enable = () => {
                this.isDisabled = false;
                [...this.element.elements]
                    .filter((element) => {
                    return ((element instanceof HTMLButtonElement ||
                        element instanceof HTMLInputElement) &&
                        element.type === "submit");
                })
                    .forEach((element) => {
                    element.removeAttribute("disabled");
                });
            };
        }
        window.welpodron.forms.specialorder.add = AddForm;
    }
})(window);
//# sourceMappingURL=script.js.map