<?php

class Notifications
{
    public function icon()
    {
        if (empty($this->items)) {
            $itemsstr = '					<div class="p-5 empty-item1 d-none">
                                            <div class="text-center">
                                                <span class="avatar avatar-xl rounded-2 bg-secondary-transparent">
                                                    <i class="ri-notification-off-line fs-2"></i>
                                                </span>
                                                <h6 class="fw-semibold mt-3">No New Notifications</h6>
                                            </div>
                                        </div>';
        } else {
            foreach ($this->items as $item) {
                $itemsstr .= '				<li class="dropdown-item mb-1">
                                                <div class="d-flex align-items-start">
                                                    <div class="pe-2">
                                                        <span class="avatar avatar-md bg-primary rounded-circle">
                                                        <i class="ti ti-gift fs-18"></i>
                                                        </span>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div
                                                            class="d-flex align-items-start justify-content-between">
                                                            <div>
                                                                <a href="'.$item['link'].'"
                                                                    class="mb-0 fs-13 font-weight-semibold text-dark">'.$item['title'].'</a>
                                                                <div class="p-1 text-warning">
                                                                    <span class="fs-12 me-2"><i
                                                                            class="bi bi-folder2-open me-1 fs-14"></i>
                                                                            '.$item['content'].'
                                                                            </span>
                                                                    <span class="fs-13">
                                                                    <i class="bi bi-download"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <a href="javascript:void(0);"
                                                                class="min-w-fit-content text-muted text-opacity-75 ms-1 dropdown-item-close1"><i
                                                                    class="ti ti-x fs-16"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>';
            }
        }

        return '				<!-- Start::header-element -->
                                <div class="header-element dropdown-center notifications-dropdown">
                                    <!-- Start::header-link|dropdown-toggle -->
                                    <a href="javascript:void(0);" class="header-link dropdown-toggle"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon"
                                            viewBox="0 0 24 24">
                                            <path d="M0 0h24v24H0V0z" fill="none" />
                                            <path
                                                d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z" />
                                        </svg>
                                        <span
                                            class="badge bg-secondary rounded-pill header-icon-badge pulse pulse-secondary"
                                            id="notification-icon-badge">4</span>
                                    </a>
                                    <!-- End::header-link|dropdown-toggle -->
                                    <!-- Start::main-header-dropdown -->
                                    <div class="main-header-dropdown dropdown-menu dropdown-menu-end"
                                        data-popper-placement="none">
                                        <div class="p-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <p class="mb-0 fs-15 fw-semibold">Notifications</p>
                                                <a href="javascript:void(0);" class="badge bg-secondary-transparent"
                                                    id="notifiation-data">4 Items</a>
                                            </div>
                                        </div>
                                        <div class="dropdown-divider my-0"></div>
                                        <ul class="list-unstyled mb-0">
                                            '.$itemsstr.'
                                        </ul>
                                        <!-- <div class="dropdown-divider"></div> -->
                                        <div class="p-3 empty-header-item1">
                                            <div class="d-grid">
                                                <a href="notify-list.html" class="btn btn-primary">View All</a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    <!-- End::main-header-dropdown -->
                                </div>
                                <!-- End::header-element -->';
    }
}
