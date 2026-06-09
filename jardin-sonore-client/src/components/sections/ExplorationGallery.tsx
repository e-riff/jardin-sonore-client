'use client';

import {ArrowLeftIcon, ArrowRightIcon} from "@heroicons/react/24/outline";
import {JSX, TouchEvent, useEffect, useMemo, useRef, useState} from "react";
import PhotoTile from "@/components/PhotoTile";
import {ExplorationPhotoGroup} from "@/types/content";

interface ExplorationGalleryProps {
    content: {
        photoGroups: readonly ExplorationPhotoGroup[];
    };
}

type SlideDirection = "previous" | "next";

interface SlideTransition {
    direction: SlideDirection;
    phase: "ready" | "sliding";
    targetIndex: number;
}

function PhotoGrid({group}: {group: ExplorationPhotoGroup | undefined}): JSX.Element | null {
    const [featuredPhoto, ...secondaryPhotos] = group?.images ?? [];

    if (!group || !featuredPhoto) {
        return null;
    }

    return (
        <div className="grid grid-cols-2 gap-3 sm:gap-gutter md:grid-cols-12">
            <PhotoTile
                {...featuredPhoto}
                title={group.title}
                description={group.subtitle}
                className="col-span-2 aspect-4/3 md:col-span-7 md:row-span-2 md:aspect-auto md:min-h-150"
                featured
                sizes="(min-width: 768px) 58vw, 100vw"
            />
            {secondaryPhotos.map((photo) => (
                <PhotoTile
                    {...photo}
                    className="aspect-square md:col-span-5 md:aspect-auto md:min-h-72"
                    key={photo.imageSrc}
                    sizes="(min-width: 768px) 42vw, 50vw"
                />
            ))}
        </div>
    );
}

export default function ExplorationGallery({content}: ExplorationGalleryProps): JSX.Element {
    const [activeGroupIndex, setActiveGroupIndex] = useState<number>(0);
    const [slideTransition, setSlideTransition] = useState<SlideTransition | null>(null);
    const touchStartRef = useRef<{x: number; y: number} | null>(null);
    const photoGroups = content.photoGroups;
    const canNavigate = photoGroups.length > 1;

    const activeGroup = photoGroups[activeGroupIndex];
    const targetGroup = useMemo(() => {
        if (!slideTransition) {
            return undefined;
        }

        return photoGroups[slideTransition.targetIndex];
    }, [photoGroups, slideTransition]);

    useEffect(() => {
        if (!slideTransition || slideTransition.phase !== "ready") {
            return;
        }

        const animationFrame = window.requestAnimationFrame(() => {
            setSlideTransition((currentTransition) => currentTransition ? {...currentTransition, phase: "sliding"} : null);
        });

        return () => window.cancelAnimationFrame(animationFrame);
    }, [slideTransition]);

    const showPhoto = (direction: SlideDirection): void => {
        if (slideTransition || !canNavigate) {
            return;
        }

        const offset = direction === "previous" ? -1 : 1;
        const targetIndex = (activeGroupIndex + offset + photoGroups.length) % photoGroups.length;
        setSlideTransition({direction, phase: "ready", targetIndex});
    };

    const handleTouchStart = (event: TouchEvent<HTMLDivElement>): void => {
        const touch = event.touches[0];
        touchStartRef.current = {x: touch.clientX, y: touch.clientY};
    };

    const handleTouchEnd = (event: TouchEvent<HTMLDivElement>): void => {
        const touchStart = touchStartRef.current;
        const touch = event.changedTouches[0];
        touchStartRef.current = null;

        if (!touchStart || !touch) {
            return;
        }

        const deltaX = touch.clientX - touchStart.x;
        const deltaY = touch.clientY - touchStart.y;
        const isHorizontalSwipe = Math.abs(deltaX) > 48 && Math.abs(deltaX) > Math.abs(deltaY);

        if (!isHorizontalSwipe) {
            return;
        }

        showPhoto(deltaX < 0 ? "next" : "previous");
    };

    return (
        <div>
            {activeGroup ? (
                <div className="relative touch-pan-y overflow-hidden" onTouchStart={handleTouchStart} onTouchEnd={handleTouchEnd}>
                    {canNavigate ? (
                        <div className="absolute right-3 top-3 z-20 flex gap-2 rounded-full bg-surface/88 p-1.5 shadow-lg backdrop-blur-md sm:right-5 sm:top-5" aria-label="Navigation des photos">
                            <button
                                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-outline-variant/70 text-primary transition hover:border-primary hover:bg-primary hover:text-on-primary disabled:opacity-60 sm:h-11 sm:w-11"
                                type="button"
                                aria-label="Photo précédente"
                                disabled={Boolean(slideTransition)}
                                onClick={() => showPhoto("previous")}
                            >
                                <ArrowLeftIcon className="h-5 w-5" />
                            </button>
                            <button
                                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-outline-variant/70 text-primary transition hover:border-primary hover:bg-primary hover:text-on-primary disabled:opacity-60 sm:h-11 sm:w-11"
                                type="button"
                                aria-label="Photo suivante"
                                disabled={Boolean(slideTransition)}
                                onClick={() => showPhoto("next")}
                            >
                                <ArrowRightIcon className="h-5 w-5" />
                            </button>
                        </div>
                    ) : null}
                    <div className="grid">
                        <div
                            className={`col-start-1 row-start-1 ${
                                slideTransition ? "transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none" : ""
                            } ${
                                slideTransition?.phase === "sliding"
                                    ? slideTransition.direction === "next" ? "-translate-x-full" : "translate-x-full"
                                    : "translate-x-0"
                            }`}
                            aria-hidden={Boolean(slideTransition)}
                        >
                            <PhotoGrid group={activeGroup} />
                        </div>
                        {slideTransition ? (
                            <div
                                className={`col-start-1 row-start-1 transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none ${
                                    slideTransition.phase === "sliding"
                                        ? "translate-x-0"
                                        : slideTransition.direction === "next" ? "translate-x-full" : "-translate-x-full"
                                }`}
                                onTransitionEnd={(event) => {
                                    if (event.target !== event.currentTarget) {
                                        return;
                                    }

                                    setActiveGroupIndex(slideTransition.targetIndex);
                                    setSlideTransition(null);
                                }}
                            >
                                <PhotoGrid group={targetGroup} />
                            </div>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
